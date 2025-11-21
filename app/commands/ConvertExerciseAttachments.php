<?php

namespace App\Console;

use App\Model\Entity\Assignment;
use App\Model\Entity\AttachmentFile;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseFile;
use App\Model\Entity\ExerciseFileLink;
use App\Model\Entity\Group;
use App\Model\Entity\GroupMembership;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\LocalizedGroup;
use App\Model\Repository\Exercises;
use App\Model\Repository\ExerciseFiles;
use App\Model\Repository\ExerciseFileLinks;
use App\Helpers\FileStorageManager;
use App\Security\Roles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Special (one-time) command for data conversion after upgrading to ReCodEx core-api 2.19.0.
 * It copies exercise attachments to exercise files and creates corresponding file links.
 *
 * Processing only non-deleted exercises and assignments which are in sync (text + attachments) with their exercises.
 * Assignments which are not synced (and have attachments and are not in archived groups) will be reported (in CSV).
 *
 * Possible issues with attachment files:
 * - missing from the storage
 *   - if the file is not used, we just skip it silently
 *   - if it is used, we report it (warning + list of missing files can be saved to CSV)
 * - unused files in the exercise text will be copied normally, but we generate a warning (list can be saved to CSV)
 * - files that already exist in the exercise files (by content hash) will be deduplicated
 *   (if multiple such files exist, the one with the most similar name is used)
 *   (if the names differ, the link will use link's save-as to preserve old name)
 * - name conflicts during copying (exercise file with the same name but different content hash already exists) are
 *   resolved by renaming the copied file adding a suffix before the extension (link's save-as preserves the old name)
 */
#[AsCommand(
    name: 'exercises:convert-attachments',
    description: 'Special (one-time) command for data conversion after upgrading to ReCodEx core-api 2.19.0. '
        . 'It copies exercise attachments to exercise files and creates corresponding file links. This operation '
        . 'needs to be done only once. The command (and the attachment files) will be removed in the future.',
)]
class ConvertExerciseAttachments extends BaseCommand
{
    /** @var Exercises */
    private $exercises;

    /** @var ExerciseFiles */
    private $exerciseFiles;

    /** @var ExerciseFileLinks */
    private $exerciseFileLinks;

    /** @var FileStorageManager */
    private $fileStorageManager;

    /*
     * Parameters
     */

    /**
     * If true, perform the changes for real; otherwise just simulate them.
     */
    private bool $forReal = false;

    /** Where the ReCodEx API is located (URL prefix) */
    private string $apuBase = '';

    /*
     * Logs and statistics
     */

    /**
     * Log of attachment files that are missing (although they are used) in the storage.
     * [ exerciseId => [ attachmentFile, ... ], ... ]
     */
    private array $missingFiles = [];

    /**
     * Log of invalid links detected in exercises.
     * [ exerciseId => [ link => error-description, ... ]
     */
    private array $invalidLinks = [];

    /**
     * Log of assignments that cannot be updated because they are not in sync with their exercises.
     * (only localized texts and attachment files are considered for sync check)
     */
    private array $notSyncedAssignments = [];

    /**
     * Number of skipped exercises (because they already have file links).
     */
    private int $skipped = 0;

    /**
     * Total number of files copied from attachment files to exercise files.
     */
    private int $filesCopied = 0;

    /**
     * Total number of files renamed due to name collisions (when being copied).
     */
    private int $filesRenamed = 0;

    /**
     * Total number of files associated with existing exercise files (deduplicated without copying).
     */
    private int $filesDeduplicated = 0;

    /**
     * Total number of exercise and assignment file links created.
     */
    private int $linksCreated = 0;

    public function __construct(
        string $apuBase,
        Exercises $exercises,
        ExerciseFiles $exerciseFiles,
        ExerciseFileLinks $exerciseFileLinks,
        FileStorageManager $fileStorageManager
    ) {
        parent::__construct();
        $this->apuBase = rtrim($apuBase, '/');
        $this->exercises = $exercises;
        $this->exerciseFiles = $exerciseFiles;
        $this->exerciseFileLinks = $exerciseFileLinks;
        $this->fileStorageManager = $fileStorageManager;
    }

    protected function configure()
    {
        $this->addOption(
            'apiBase',
            null,
            InputOption::VALUE_REQUIRED,
            'Location of the ReCodEx API (URL prefix) used for detection of the attachment file links.',
            null
        );
        $this->addOption(
            'forReal',
            null,
            InputOption::VALUE_NONE,
            'If set, the changes are performed for real; otherwise just a simulation is done.'
        );
        $this->addOption(
            'exercise',
            null,
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'If set, only the exercises with the given IDs are processed (multiple IDs can be specified).',
            null
        );
        $this->addOption(
            'missingLog',
            null,
            InputOption::VALUE_REQUIRED,
            'CSV file where missing attachment files are logged (only those that are used in texts).',
            null
        );
        $this->addOption(
            'invalidLog',
            null,
            InputOption::VALUE_REQUIRED,
            'CSV file where invalid attachment file links are logged.',
            null
        );
        $this->addOption(
            'notSyncedLog',
            null,
            InputOption::VALUE_REQUIRED,
            'CSV file where assignments that are not in sync with their exercises are logged.',
            null
        );
    }

    /**
     * Returns all attachment files of the given exercise indexed by their ID.
     * @param Exercise $exercise
     * @return array [ attachment-file-id => AttachmentFile, ... ]
     */
    private function getAttachmentFiles(Exercise $exercise): array
    {
        $files = [];
        foreach ($exercise->getAttachmentFiles() as $file) {
            /** @var AttachmentFile $file */
            $files[$file->getId()] = $file;
        }
        return $files;
    }

    /**
     * Returns all exercise files of the given exercise indexed by their ID.
     * @param Exercise $exercise
     * @return array [ exercise-file-id => ExerciseFile, ... ]
     */
    private function getExerciseFilesByName(Exercise $exercise): array
    {
        $files = [];
        foreach ($exercise->getExerciseFiles() as $file) {
            /** @var ExerciseFile $file */
            $files[$file->getName()] = $file;
        }
        return $files;
    }

    /**
     * Return physical file wrappers (IImmutableFile) for all attachment files.
     * @param array $attachmentFiles [ attachment-file-id => IImmutableFile, ... ]
     */
    private function getAttachmentFilesImmutableFiles(array $attachmentFiles): array
    {
        $immutableFiles = [];
        foreach ($attachmentFiles as $id => $file) {
            /** @var AttachmentFile $file */
            $immutableFiles[$id] = $this->fileStorageManager->getAttachmentFile($file);
        }
        return $immutableFiles;
    }

    /**
     * Extracts all attachment file links from the given exercise texts.
     * Bad links are logged in global $invalidLinks.
     * @param Exercise $exercise
     * @param array|null $attachmentFiles attachment files indexed by their ID
     * @return array [ link-url => attachment-file-id, ... ]
     */
    private function extractAllLinksFromText(Exercise $exercise, ?array $attachmentFiles = null): array
    {
        $links = [];
        if ($attachmentFiles === null) {
            $attachmentFiles = $this->getAttachmentFiles($exercise);
        }

        $reg = '#https://(?<base>[-_a-zA-Z0-9./:]+)/v1/uploaded-files/(?<id>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/download#';
        foreach ($exercise->getLocalizedTexts() as $localizedText) {
            /** @var LocalizedExercise $localizedText */
            $text = $localizedText->getAssignmentText() . "\n" . $localizedText->getDescription();
            if (!preg_match_all($reg, $text, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $base = "https://" . $match['base'];
                if ($base !== $this->apuBase) {
                    // report bad link
                    $this->invalidLinks[$exercise->getId()][$match[0]] = "invalid base URL";
                    continue;
                }

                if (!array_key_exists($match['id'], $attachmentFiles)) {
                    // report bad link
                    $this->invalidLinks[$exercise->getId()][$match[0]] = "invalid attachment file ID";
                    continue;
                }

                $links[$match[0]] = $match['id'];
            }
        }

        $this->writeDebug(
            "Extracted " . count($links) . " attachment links from exercise '{$exercise->getId()}'."
        );
        return $links;
    }

    /**
     * Detects which attachment files are used in the given links.
     * @param array $attachmentFiles [ attachment-file-id => AttachmentFile, ... ]
     * @param array $textLinks [ link-url => attachment-file-id, ... ]
     * @return array [ attachment-file-id => bool (is-used), ... ]
     */
    private function detectUsedAttachmentFiles(array $attachmentFiles, array $textLinks): array
    {
        $used = [];
        foreach (array_keys($attachmentFiles) as $id) {
            $used[$id] = false;
        }
        foreach ($textLinks as $attachmentFileId) {
            $used[$attachmentFileId] = true;
        }
        return $used;
    }

    /**
     * Finds an exercise file in the given list that matches the given attachment file by content hash.
     * If multiple files match, the one with the most similar name is returned.
     * @param AttachmentFile $attachmentFile
     * @param array $exerciseFiles [ name => ExerciseFile, ... ]
     * @return ExerciseFile|null (null if no matching file is found)
     */
    private function findExerciseFileDuplicate(
        AttachmentFile $attachmentFile,
        array $exerciseFiles,
    ): ?ExerciseFile {
        $hash = $this->fileStorageManager->getAttachmentFileHash($attachmentFile);
        $name = $attachmentFile->getName();
        $candidates = [];
        foreach ($exerciseFiles as $name => $exerciseFile) {
            /** @var ExerciseFile $exerciseFile */
            if ($exerciseFile instanceof ExerciseFile && $exerciseFile->getHashName() === $hash) {
                $candidates[$name] = $exerciseFile;
            }
        }

        if (!$candidates) {
            return null;
        }

        if (array_key_exists($name, $candidates)) {
            return $candidates[$name];
        }

        $best = reset($candidates);
        if (count($candidates) > 1) {
            $cost = PHP_INT_MAX;
            foreach ($candidates as $candidateName => $candidate) {
                $currentCost = levenshtein($name, $candidateName);
                if ($currentCost < $cost) {
                    $cost = $currentCost;
                    $best = $candidate;
                }
            }
        }

        $this->writeVerbose(
            "Attachment file '{$attachmentFile->getId()}' named '$name' matches existing exercise file "
                . "'{$best->getId()}' named '{$best->getName()}' [deduplicated]."
        );
        $this->filesDeduplicated++;
        return $best;
    }

    /**
     * Creates a new exercise file from the given attachment file for the given exercise.
     * The created exercise file is added to the $exerciseFiles array (indexed by name).
     * In case of name collision, the name is modified by adding suffix before the extension.
     * @param AttachmentFile $attachmentFile
     * @param Exercise $exercise
     * @param array $exerciseFiles [ name => ExerciseFile, ... ] modified in place
     * @return ExerciseFile|string the created exercise file (or its name in simulation mode)
     */
    private function createExerciseFileFromAttachment(
        AttachmentFile $attachmentFile,
        Exercise $exercise,
        array &$exerciseFiles
    ): mixed {
        $name = $attachmentFile->getName();
        if (array_key_exists($name, $exerciseFiles)) {
            // rename the file in case of a collision
            $base = pathinfo($name, PATHINFO_FILENAME) . '_attach';
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $counter = '';
            do {
                $name = "$base$counter.$ext";
                $counter = (int)$counter + 1;
            } while (array_key_exists($name, $exerciseFiles));
        }

        $this->writeComment(
            "Copying attachment file '{$attachmentFile->getId()}' named '{$attachmentFile->getName()}' "
                . "as new exercise file named '$name' ..."
        );
        if ($this->forReal) {
            $hash = $this->fileStorageManager->copyAttachmentFileToHashStorage($attachmentFile);

            $exerciseFile = new ExerciseFile(
                $name,
                $attachmentFile->getUploadedAt(),
                $attachmentFile->getFileSize(),
                $hash,
                $attachmentFile->getUser(),
                $exercise
            );
            $this->exerciseFiles->persist($exerciseFile);
        } else {
            $exerciseFile = $name; // dummy (we need to remember the name only)
        }

        $exerciseFiles[$name] = $exerciseFile;
        $this->filesCopied++;
        return $exerciseFile;
    }

    /**
     * Creates exercise file links for the given attachment files mapped to exercise files.
     * Only attachment files present in the mapping are processed.
     * @param array $attachmentFiles [ attachment-file-id => AttachmentFile, ... ]
     * @param array $mapping [ attachment-file-id => ExerciseFile, ... ]
     * @param Exercise $exercise
     * @return array [ attachment-id => ExerciseFileLink, ... ]
     *               a link key (instead of ExerciseFileLink) is used as value in the simulation mode
     */
    private function createExerciseLinks(array $attachmentFiles, array $mapping, Exercise $exercise): array
    {
        $links = [];
        $usedKeys = [];
        foreach ($mapping as $id => $exerciseFile) {
            /** @var AttachmentFile $attachmentFile */
            $attachmentFile = $attachmentFiles[$id];
            $attachmentName = $attachmentFile->getName();
            $key = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($attachmentName, PATHINFO_FILENAME)));
            $key = $key ? $key : 'FILE';
            $key = substr($key, 0, 14);

            // make sure the key is unique
            $baseKey = $key;
            $counter = 0;
            while (array_key_exists($key, $usedKeys)) {
                ++$counter;
                while (strlen("{$baseKey}_{$counter}") > 16) {
                    $baseKey = substr($baseKey, 0, -1);
                }
                $key = "{$baseKey}_{$counter}";
            }
            $usedKeys[$key] = true;

            if ($this->forReal) {
                $saveAs = $exerciseFile->getName() !== $attachmentName ? $attachmentName : null;
                $this->filesRenamed += $saveAs ? 1 : 0;

                $saveAsStr = $saveAs ? "(to be saved as '$saveAs') " : '';
                $this->writeComment(
                    "Creating exercise ('{$exercise->getId()}') link for file '{$exerciseFile->getId()}' "
                        . "named '{$exerciseFile->getName()}' with key '$key' $saveAsStr..."
                );

                $links[$id] = ExerciseFileLink::createForExercise(
                    $key,
                    $exerciseFile,
                    $exercise,
                    Roles::STUDENT_ROLE,
                    $saveAs
                );
                $this->exerciseFileLinks->persist($links[$id], false);
            } else {
                if ($exerciseFile instanceof ExerciseFile) {
                    $exerciseFile = $exerciseFile->getName();
                }
                $saveAsStr = ($exerciseFile !== $attachmentName) ? "(to be saved as '$attachmentName') " : '';
                $this->writeComment(
                    "Creating exercise ('{$exercise->getId()}') link for file named '$exerciseFile' "
                        . "with key '$key' $saveAsStr..."
                );

                // just a simulation, make sure the counters are correct
                $this->filesRenamed += ($exerciseFile !== $attachmentName) ? 1 : 0;
                $links[$id] = $key;
            }

            $this->linksCreated++;
        }

        if ($this->forReal) {
            $this->exerciseFileLinks->flush();
        }

        return $links;
    }

    /**
     * Copy exercise file links for the given assignment.
     * @param Assignment $assignment (that needs to be in sync with its exercise)
     * @param ExerciseFileLink[] $links
     */
    private function createAssignmentsLinks(Assignment $assignment, array $links): void
    {
        $this->writeComment("Creating links for assignment '{$assignment->getId()}' ...");
        $this->linksCreated += count($links);

        if ($this->forReal) {
            $assignment->getFileLinks()->clear(); // remove old links
            foreach ($links as $link) {
                $assignmentLink = ExerciseFileLink::copyForAssignment(
                    $link,
                    $assignment,
                );
                $this->exerciseFileLinks->persist($assignmentLink, false);
            }
            $this->exerciseFileLinks->flush();
        }

        $this->writeDebug("Created " . count($links) . " links for the assignment.");
    }

    /**
     * Updates the texts of the given exercise to use the given links instead of direct URLs.
     * Each link is replaced by %%key%% where key is the key of the corresponding ExerciseFileLink.
     * @param Exercise $exercise
     * @param array $textLinks [ link-url => attachment-file-id, ... ]
     * @param array $links [ attachment-file-id => ExerciseFileLink, ... ]
     */
    private function updateExerciseTexts(
        Exercise $exercise,
        array $textLinks,
        array $links,
    ): void {

        // prepare search and replace arrays for collective replacement
        $search = [];
        $replace = [];
        foreach ($textLinks as $url => $id) {
            $search[] = $url;
            $key = ($links[$id] instanceof ExerciseFileLink) ? $links[$id]->getKey() : (string)$links[$id];
            $replace[] = "%%$key%%";
            $this->writeDebug("Replacing '$url' with '%%$key%%' in exercise '{$exercise->getId()}'");
        }

        $this->writeComment("Updating texts for exercise '{$exercise->getId()}' (" . count($textLinks)
            . " replacements) ...");

        if ($this->forReal) {
            foreach ($exercise->getLocalizedTexts() as $localizedText) {
                /** @var LocalizedExercise $localizedText */
                $newAssignmentText = str_replace($search, $replace, $localizedText->getAssignmentText());
                $localizedText->setAssignmentTextDangerous($newAssignmentText);
                $newDescription = str_replace($search, $replace, $localizedText->getDescription());
                $localizedText->setDescriptionDangerous($newDescription);
                $this->exercises->persist($localizedText);
            }
        }
    }

    /**
     * Returns assignments of the exercise which are in sync with it (localized texts + attachment files).
     * Assignments in archived groups are ignored. Assignments not in sync are logged in $notSyncedAssignments.
     * @param Exercise $exercise
     * @return array [ assignmentId => Assignment, ... ]
     */
    private function getSyncedAssignments(Exercise $exercise): array
    {
        $assignments = [];
        foreach ($exercise->getAssignments() as $assignment) {
            if ($assignment->getGroup() === null || $assignment->getGroup()->isArchived()) {
                $this->writeDebug(
                    "Skipping out-of-sync assignment '{$assignment->getId()}' in archived or deleted group."
                );
                continue;
            }

            if (!$assignment->areLocalizedTextsInSync() || !$assignment->areAttachmentFilesInSync()) {
                $this->writeWarning("Skipping <info>assignment '{$assignment->getId()}'</> since it is not in sync.");
                $this->notSyncedAssignments[] = $assignment;
                continue;
            }

            $assignments[$assignment->getId()] = $assignment;
        }
        return $assignments;
    }

    /**
     * Processes a single exercise.
     * All existing attachment files are copied to exercise files and corresponding links are created.
     * The exercise texts are updated to use the links (by keys) instead of direct URLs.
     * The links are then copied to all assignments which are in sync with the exercise.
     * Notes:
     * - missing attachment files used in the texts are reported in $missingFiles (not used files are ignored)
     * - files are deduplicated based on content hash (using a match with the most similar name)
     * - attachment names conflicting with existing exercise files are renamed
     *   (original names are preserved in the links using save-as feature for renaming)
     * @param Exercise $exercise
     */
    protected function processExercise(Exercise $exercise): void
    {
        // get info about the exercise files
        $attachmentFiles = $this->getAttachmentFiles($exercise);
        $exerciseFiles = $origExerciseFiles = $this->getExerciseFilesByName($exercise);
        $textLinks = $this->extractAllLinksFromText($exercise, $attachmentFiles);
        $used = $this->detectUsedAttachmentFiles($attachmentFiles, $textLinks);
        $physicalFiles = $this->getAttachmentFilesImmutableFiles($attachmentFiles);

        // process all attachment files, create/associate corresponding exercise files
        $attachmentExerciseFileMap = []; // attachment-id => ExerciseFile
        foreach ($attachmentFiles as $id => $attachmentFile) {
            if (!$physicalFiles[$id]) { // file is missing
                if ($used[$id]) {
                    $this->writeWarning(
                        "Attachment file '{$id}' is missing but used in exercise '{$exercise->getId()}'."
                    );
                    $this->missingFiles[$exercise->getId()][] = $attachmentFile;
                } else {
                    $this->writeComment(
                        "Attachment file '{$id}' is missing but not used in exercise '{$exercise->getId()}'; skipping."
                    );
                }
                continue;
            }

            $this->writeDebug(
                "Processing attachment file '{$id}' named '{$attachmentFile->getName()}' ..."
            );
            $exerciseFile = $this->findExerciseFileDuplicate($attachmentFile, $origExerciseFiles);
            if ($exerciseFile === null) {
                $exerciseFile = $this->createExerciseFileFromAttachment($attachmentFile, $exercise, $exerciseFiles);
            }
            $attachmentExerciseFileMap[$id] = $exerciseFile;
        }

        $links = $this->createExerciseLinks($attachmentFiles, $attachmentExerciseFileMap, $exercise);

        // this updates the exercise texts in-place, so the assignments in sync also see the update
        $this->updateExerciseTexts($exercise, $textLinks, $links);

        // create a copy of links for all synced assignments
        foreach ($this->getSyncedAssignments($exercise) as $assignment) {
            /** @var Assignment $assignment */
            $this->createAssignmentsLinks($assignment, $links);
        }
    }

    /**
     * Prints the final conversion statistics.
     */
    private function printStatistics(int $totalExercises): void
    {
        $this->output->writeln("<info>=== Conversion statistics ===</>");
        $this->output->writeln("Exercises:           {$totalExercises} (skipped: {$this->skipped})");
        $this->output->writeln("Files copied:        {$this->filesCopied}");
        $this->output->writeln(
            "Files renamed:       {$this->filesRenamed}",
            $this->filesRenamed ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE
        );
        $this->output->writeln(
            "Files deduplicated:  {$this->filesDeduplicated}",
            $this->filesDeduplicated ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE
        );
        $this->output->writeln("Links created:       {$this->linksCreated}");

        $missingFilesExercises = count($this->missingFiles);
        if ($missingFilesExercises > 0) {
            $this->output->writeln("Exercises missing files:      <fg=red>{$missingFilesExercises}</>");
        }

        $invalidLinkExercises = count($this->invalidLinks);
        if ($invalidLinkExercises > 0) {
            $this->output->writeln("Exercises with invalid links: <fg=red>{$invalidLinkExercises}</>");
        }

        $notSyncedAssignments = count($this->notSyncedAssignments);
        if ($notSyncedAssignments > 0) {
            $this->output->writeln("Not-synced assignments:       <fg=yellow>{$notSyncedAssignments}</>");
        }

        $this->output->writeln("<info>=============================</>");
        if (!$this->forReal) {
            $this->output->writeln(
                "<comment>NOTE: This was a simulation only; no changes were made. Use --forReal to apply changes.</>"
            );
        }
    }

    /**
     * Saves a CSV log file.
     * @param string $filePath
     * @param string[] $header (column names)
     * @param array[] $rows (each row is an associative array with column-name => value)
     */
    private function saveCsv(string $filePath, array $header, array $rows): void
    {
        $fp = fopen($filePath, 'w');
        fputcsv($fp, $header);
        foreach ($rows as $row) {
            $orderedRow = [];
            foreach ($header as $col) {
                $orderedRow[] = $row[$col] ?? '';
            }
            fputcsv($fp, $orderedRow);
        }
        fclose($fp);
    }

    /**
     * Returns the name of the given exercise (preferring English localization).
     * @param Exercise $exercise
     * @return string
     */
    private function getExerciseName(?Exercise $exercise): string
    {
        $localizedTexts = $exercise?->getLocalizedTexts();
        if ($localizedTexts === null || $localizedTexts->isEmpty()) {
            return '???';
        }
        /** @var LocalizedExercise $localizedText */
        $localizedText = $localizedTexts->first();
        foreach ($localizedTexts as $lt) {
            /** @var LocalizedExercise $lt */
            if ($lt->getLocale() === 'en') {
                $localizedText = $lt;
                break;
            }
        }
        return $localizedText->getName();
    }

    private function getGroupName(?Group $group): string
    {
        $localizedGroups = $group?->getLocalizedTexts();
        if ($localizedGroups === null || $localizedGroups->isEmpty()) {
            return '???';
        }

        $localizedGroup = $localizedGroups->first();
        foreach ($localizedGroups as $lg) {
            /** @var LocalizedGroup $lg */
            if ($lg->getLocale() === 'en') {
                $localizedGroup = $lg;
                break;
            }
        }

        return $localizedGroup->getName();
    }

    /**
     * Exports a log of missing files to a CSV file.
     * @param string $filePath
     */
    private function exportMissingFilesLog(string $filePath): void
    {
        $this->writeVerbose("Exporting missing attachment files log to '$filePath' ...");
        $rows = [];
        foreach ($this->missingFiles as $exerciseId => $files) {
            $exercise = $this->exercises->findOrThrow($exerciseId);
            /** @var AttachmentFile $file */
            foreach ($files as $file) {
                $rows[] = [
                    'exerciseId' => $exerciseId,
                    'exerciseName' => $this->getExerciseName($exercise),
                    'authorId' => $exercise->getAuthor()?->getId(),
                    'authorName' => $exercise->getAuthor()?->getFirstName() . ' '
                        . $exercise->getAuthor()?->getLastName(),
                    'authorMail' => $exercise->getAuthor()?->getEmail(),
                    'attachmentFileId' => $file->getId(),
                    'fileName' => $file->getName(),
                ];
            }
        }
        $this->saveCsv(
            $filePath,
            ['exerciseId', 'exerciseName', 'authorId', 'authorName', 'authorMail', 'attachmentFileId', 'fileName'],
            $rows
        );
    }

    /**
     * Exports a log of invalid links to a CSV file.
     * @param string $filePath
     */
    private function exportInvalidLinksLog(string $filePath): void
    {
        $this->writeVerbose("Exporting invalid attachment file links log to '$filePath' ...");
        $rows = [];
        foreach ($this->invalidLinks as $exerciseId => $links) {
            $exercise = $this->exercises->findOrThrow($exerciseId);
            foreach ($links as $link => $error) {
                $rows[] = [
                    'exerciseId' => $exerciseId,
                    'exerciseName' => $this->getExerciseName($exercise),
                    'authorId' => $exercise->getAuthor()?->getId(),
                    'authorName' => $exercise->getAuthor()?->getFirstName() . ' '
                        . $exercise->getAuthor()?->getLastName(),
                    'authorMail' => $exercise->getAuthor()?->getEmail(),
                    'link' => $link,
                    'error' => $error,
                ];
            }
        }
        $this->saveCsv(
            $filePath,
            ['exerciseId', 'exerciseName', 'authorId', 'authorName', 'authorMail', 'link', 'error'],
            $rows
        );
    }

    private function exportNotSyncedAssignmentsLog(string $filePath): void
    {
        $this->writeVerbose("Exporting not-synced assignments log to '$filePath' ...");
        $rows = [];
        foreach ($this->notSyncedAssignments as $assignment) {
            /** @var Assignment $assignment */
            $exercise = $assignment->getExercise();
            $admins = [];
            foreach ($assignment->getGroup()?->getMemberships(GroupMembership::TYPE_ADMIN) ?? [] as $membership) {
                /** @var GroupMembership $membership */
                $admins[] = $membership->getUser()->getFirstName() . ' ' . $membership->getUser()->getLastName()
                    . " <" . $membership->getUser()->getEmail() . ">";
            }
            $rows[] = [
                'assignmentId' => $assignment->getId(),
                'groupId' => $assignment->getGroup()?->getId(),
                'groupName' => $this->getGroupName($assignment->getGroup()),
                'groupAdmins' => implode('|', $admins),
                'exerciseId' => $exercise?->getId(),
                'exerciseName' => $this->getExerciseName($exercise),
                'authorId' => $exercise->getAuthor()?->getId(),
                'authorName' => $exercise->getAuthor()?->getFirstName() . ' '
                    . $exercise->getAuthor()?->getLastName(),
                'authorMail' => $exercise->getAuthor()?->getEmail(),
            ];
        }
        $this->saveCsv(
            $filePath,
            [
                'assignmentId',
                'groupId',
                'groupName',
                'groupAdmins',
                'exerciseId',
                'exerciseName',
                'authorId',
                'authorName',
                'authorMail'
            ],
            $rows
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        // get options
        $apuBase = $this->input->getOption('apiBase');
        if ($apuBase) {
            $this->apuBase = rtrim($apuBase, '/');
        }
        $this->forReal = (bool)$this->input->getOption('forReal');

        $exerciseIds = $this->input->getOption('exercise');
        if ($exerciseIds !== null && !is_array($exerciseIds)) {
            $exerciseIds = [$exerciseIds];
        }
        if ($exerciseIds) {
            $this->writeVerbose('Processing only exercises: ' . implode(', ', $exerciseIds));
            $exercises = array_map(function ($id) {
                return $this->exercises->findOrThrow($id);
            }, $exerciseIds);
        } else {
            $exercises = $this->exercises->findAll();
        }

        // process exercises
        foreach ($exercises as $exercise) {
            if (!$exercise->getFileLinks()->isEmpty()) {
                ++$this->skipped;
                $this->writeComment(
                    "Skipping exercise '{$exercise->getId()}' since it already has file links (already processed)."
                );
                continue;
            }

            if ($exercise->getAttachmentFiles()->isEmpty()) {
                // just check whether there are any invalid links in the specification
                $this->writeDebug("Processing exercise '{$exercise->getId()}' (just checking links) ...");
                $this->extractAllLinksFromText($exercise);
            } else {
                // complete exercise processing
                $this->writeDebug("Processing exercise '{$exercise->getId()}' (with attachments) ...");
                $this->processExercise($exercise);
            }
        }

        // print final statistics and dump logs
        $this->printStatistics(count($exercises));

        if ($this->input->getOption('missingLog')) {
            $this->exportMissingFilesLog($this->input->getOption('missingLog'));
        }

        if ($this->input->getOption('invalidLog')) {
            $this->exportInvalidLinksLog($this->input->getOption('invalidLog'));
        }

        if ($this->input->getOption('notSyncedLog')) {
            $this->exportNotSyncedAssignmentsLog($this->input->getOption('notSyncedLog'));
        }

        return Command::SUCCESS;
    }
}
