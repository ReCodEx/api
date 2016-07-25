-- Adminer 4.2.5 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `exercise`;
CREATE TABLE `exercise` (
  `id` varchar(36) NOT NULL,
  `exercise_id` varchar(36) DEFAULT NULL,
  `name` varchar(36) NOT NULL,
  `version` int(11) NOT NULL,
  `user_id` varchar(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `description` text NOT NULL,
  `difficulty` enum('trivial','easy','medium','hard','nearly_unsolvable') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `exercise_id` (`exercise_id`),
  CONSTRAINT `exercise_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `exercise_ibfk_2` FOREIGN KEY (`exercise_id`) REFERENCES `exercise` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `exercise` (`id`, `exercise_id`, `name`, `version`, `user_id`, `created_at`, `updated_at`, `description`, `difficulty`) VALUES
('ce1f2a4a-50e7-11e6-beb8-9e71128cae77',	NULL,	'Hrošíci',	1,	'1fe2255e-50e2-11e6-beb8-9e71128cae77',	'2016-07-23 15:13:20',	'2016-07-23 15:13:20',	'An h1 header\r\n============\r\n\r\nParagraphs are separated by a blank line.\r\n\r\n2nd paragraph. *Italic*, **bold**, and `monospace`. Itemized lists\r\nlook like:\r\n\r\n  * this one\r\n  * that one\r\n  * the other one\r\n\r\nNote that --- not considering the asterisk --- the actual text\r\ncontent starts at 4-columns in.\r\n\r\n> Block quotes are\r\n> written like so.\r\n>\r\n> They can span multiple paragraphs,\r\n> if you like.\r\n\r\nUse 3 dashes for an em-dash. Use 2 dashes for ranges (ex., \"it\'s all\r\nin chapters 12--14\"). Three dots ... will be converted to an ellipsis.\r\nUnicode is supported. ☺\r\n\r\n\r\n\r\nAn h2 header\r\n------------\r\n\r\nHere\'s a numbered list:\r\n\r\n 1. first item\r\n 2. second item\r\n 3. third item\r\n\r\nNote again how the actual text starts at 4 columns in (4 characters\r\nfrom the left side). Here\'s a code sample:\r\n\r\n    # Let me re-iterate ...\r\n    for i in 1 .. 10 { do-something(i) }',	'nearly_unsolvable');

DROP TABLE IF EXISTS `exercise_assignment`;
CREATE TABLE `exercise_assignment` (
  `id` varchar(36) NOT NULL,
  `group_id` varchar(36) NOT NULL,
  `exercise_id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `job_config_file_path` varchar(100) NOT NULL,
  `description` text,
  `first_deadline` timestamp NOT NULL,
  `max_points_before_first_deadline` smallint(6) NOT NULL,
  `second_deadline` timestamp NOT NULL,
  `max_points_before_second_deadline` smallint(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `exercise_assignment_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `exercise_assignment` (`id`, `group_id`, `exercise_id`, `name`, `job_config_file_path`, `description`, `first_deadline`, `max_points_before_first_deadline`, `second_deadline`, `max_points_before_second_deadline`) VALUES
('25f248c4-50e8-11e6-beb8-9e71128cae77',	'5c38c32a-50e5-11e6-beb8-9e71128cae77',	'ce1f2a4a-50e7-11e6-beb8-9e71128cae77',	'Hrošíci pro dlouhé letní večery',	'C:\\Users\\simon-desktop\\Documents\\Github\\recodex\\api\\exercises\\hrosi-ohradka\\submit\\job-config.yml',	NULL,	'2016-08-23 15:17:40',	10,	'2016-08-31 21:59:59',	7);

DROP TABLE IF EXISTS `group`;
CREATE TABLE `group` (
  `id` varchar(36) NOT NULL,
  `name` varchar(50) NOT NULL,
  `parent_group_id` varchar(36) DEFAULT NULL,
  `instance_id` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_group_id` (`parent_group_id`),
  KEY `instance_id` (`instance_id`),
  CONSTRAINT `group_ibfk_1` FOREIGN KEY (`parent_group_id`) REFERENCES `group` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `group_ibfk_2` FOREIGN KEY (`instance_id`) REFERENCES `instance` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `group` (`id`, `name`, `parent_group_id`, `instance_id`) VALUES
('50ebf7ee-50e5-11e6-beb8-9e71128cae77',	'Programování I',	'de171b8f-ea15-42aa-8b5a-6ff2164beccb',	NULL),
('5c38c32a-50e5-11e6-beb8-9e71128cae77',	'Programování II',	'de171b8f-ea15-42aa-8b5a-6ff2164beccb',	NULL),
('de171b8f-ea15-42aa-8b5a-6ff2164beccb',	'Akademický rok 2016/2017',	NULL,	'91f4882f-30f3-4bc8-87d2-def0e2716c94');

DROP TABLE IF EXISTS `group_student`;
CREATE TABLE `group_student` (
  `group_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `group_student_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`),
  CONSTRAINT `group_student_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `group_student` (`group_id`, `user_id`) VALUES
('5c38c32a-50e5-11e6-beb8-9e71128cae77',	'1fe2255e-50e2-11e6-beb8-9e71128cae77');

DROP TABLE IF EXISTS `group_supervisor`;
CREATE TABLE `group_supervisor` (
  `group_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `group_supervisor_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`),
  CONSTRAINT `group_supervisor_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `group_supervisor` (`group_id`, `user_id`) VALUES
('50ebf7ee-50e5-11e6-beb8-9e71128cae77',	'1fe2255e-50e2-11e6-beb8-9e71128cae77');

DROP TABLE IF EXISTS `instance`;
CREATE TABLE `instance` (
  `id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `is_open` tinyint(4) NOT NULL,
  `is_allowed` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `admin_id` varchar(36) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`admin_id`),
  CONSTRAINT `instance_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `instance` (`id`, `name`, `description`, `is_open`, `is_allowed`, `created_at`, `updated_at`, `admin_id`) VALUES
('91f4882f-30f3-4bc8-87d2-def0e2716c94',	'Matematicko-fyzikální fakulta Univerzity Karlovy',	'Matematicko-fyzikální fakulta Univerzity Karlovy patří tradičně k nejlepším vědeckým a vzdělávacím institucím celé České republiky. Nabízíme kvalitní vzdělání v širokém spektru matematických, fyzikálních, informatických a učitelských oborů. Naši studenti se v rámci výuky podílejí na mezinárodních výzkumných projektech, nebo v rámci programu Erasmus studují v zahraničí. Zhruba 6 % vědeckých výsledků (podle RIV) celé České republiky produkuje Matematicko-fyzikální fakulta Univerzity Karlovy. Taktéž v institucionálním hodnocení výkonnosti, či v mediálních žebříčcích zaujímá první místa. Studium na fakultě otevírá možnost účasti na mezinárodních projektech, například na výzkumech ve švýcarském CERN. Získané zkušenosti jsou zároveň výborným základem pro úspěšnou kariéru ve vlastním podnikání. **Absolventy MFF UK najdeme také ve firmách jako je Facebook, Oracle nebo Google.** Ročně tu absolvuje kolem 400 studentů, z nichž 96,5 % se uplatní v oboru.\r\n\r\n    Matfyz jako životní názor. Klíčem k úspěchu je myšlení, nikoli jedničky z matematiky. Naši učitelé jsou mezinárodně aktivní vědci, náš přístup k vám je individuální a všechna pracoviště jsou špičkově vybavená.\r\n\r\nAbsolventi fakulty nemají problém rozjet vlastní podnikání, snadno se uplatní v malých i velkých firmách a mnoho z nich se samozřejmě rozhodne pro vědeckou kariéru; absolventi učitelských oborů předávají své znalosti dětem na základních i středních školách a povzbuzují jejich hlubší zájem o vyučované vědy. Všichni však mají něco společného. Jsou otevření světu, přicházejí s inovativními nápady a dokážou kriticky myslet. To jsou zásadní předpoklady úspěchu. Studium na MFF UK je mimořádnou osobní výzvou. Její zdolání zároveň dává záruku úspěšného startu do života. Vyučující mají ke studentům blízko a osobní přístup je jednou z dalších velkých výhod fakulty.',	0,	1,	'2016-07-25 08:49:45',	'2016-07-25 08:49:45',	'1fe2255e-50e2-11e6-beb8-9e71128cae77');

DROP TABLE IF EXISTS `licence`;
CREATE TABLE `licence` (
  `id` varchar(36) NOT NULL,
  `name` varchar(50) NOT NULL,
  `instance_id` varchar(36) NOT NULL,
  `is_valid` tinyint(4) NOT NULL,
  `valid_until` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `note` varchar(300) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `instance_id` (`instance_id`),
  CONSTRAINT `licence_ibfk_1` FOREIGN KEY (`instance_id`) REFERENCES `instance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `licence` (`id`, `name`, `instance_id`, `is_valid`, `valid_until`, `note`) VALUES
('7f5d2fc6-f7f2-4ae4-a6dd-81dfe5157555',	'Výuka na MFF UK na ak. rok 2016/2017',	'91f4882f-30f3-4bc8-87d2-def0e2716c94',	1,	'2017-09-30 21:59:59',	'');

DROP TABLE IF EXISTS `role`;
CREATE TABLE `role` (
  `id` varchar(50) NOT NULL,
  `parent_role_id` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_role_id` (`parent_role_id`),
  CONSTRAINT `role_ibfk_1` FOREIGN KEY (`parent_role_id`) REFERENCES `role` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `role` (`id`, `parent_role_id`) VALUES
('student',	NULL),
('superadmin',	'admin'),
('teacher',	'student'),
('admin',	'teacher');

DROP TABLE IF EXISTS `submission`;
CREATE TABLE `submission` (
  `id` varchar(36) NOT NULL,
  `exercise_assignment_id` varchar(36) NOT NULL,
  `submission_evaluation_id` varchar(36) DEFAULT NULL,
  `user_id` varchar(36) NOT NULL,
  `note` varchar(300) NOT NULL,
  `results_url` varchar(300) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exercise_assignment_id` (`exercise_assignment_id`),
  KEY `user_id` (`user_id`),
  KEY `submission_evaluation_id` (`submission_evaluation_id`),
  CONSTRAINT `submission_ibfk_1` FOREIGN KEY (`exercise_assignment_id`) REFERENCES `exercise_assignment` (`id`),
  CONSTRAINT `submission_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `submission_ibfk_3` FOREIGN KEY (`submission_evaluation_id`) REFERENCES `submission_evaluation` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `submission` (`id`, `exercise_assignment_id`, `submission_evaluation_id`, `user_id`, `note`, `results_url`, `submitted_at`) VALUES
('8b306e67-51b5-11e6-a145-180373206d10',	'25f248c4-50e8-11e6-beb8-9e71128cae77',	NULL,	'1fe2255e-50e2-11e6-beb8-9e71128cae77',	'bind them!!',	'http://195.113.17.8:9999/results/8b306e67-51b5-11e6-a145-180373206d10.zip',	'2016-07-24 15:44:44'),
('c95f9b6a-51bf-11e6-a145-180373206d10',	'25f248c4-50e8-11e6-beb8-9e71128cae77',	'6cfb0e40-51c1-11e6-a145-180373206d10',	'1fe2255e-50e2-11e6-beb8-9e71128cae77',	'bind them!!',	'http://195.113.17.8:9999/results/c95f9b6a-51bf-11e6-a145-180373206d10.zip',	'2016-07-24 16:58:04');

DROP TABLE IF EXISTS `submission_evaluation`;
CREATE TABLE `submission_evaluation` (
  `id` varchar(36) NOT NULL,
  `score` float NOT NULL,
  `points` int(11) NOT NULL,
  `evaluated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `result_yml` text NOT NULL,
  `is_valid` tinyint(4) NOT NULL,
  `is_correct` tinyint(4) NOT NULL,
  `evaluation_failed` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `submission_evaluation` (`id`, `score`, `points`, `evaluated_at`, `result_yml`, `is_valid`, `is_correct`, `evaluation_failed`) VALUES
('6cfb0e40-51c1-11e6-a145-180373206d10',	0,	0,	'2016-07-24 17:09:48',	'job-id: c95f9b6a-51bf-11e6-a145-180373206d10\nresults:\n  - task-id: compilation\n    status: OK\n    sandbox_results:\n      wall-time: 0.097\n      time: 0.037\n      memory: 5904\n      exitsig: 0\n      exitcode: 0\n      status: OK\n      max-rss: 19696\n      killed: false\n      message: \"\"\n  - task-id: fetch_test_1\n    status: OK\n  - status: OK\n    sandbox_results:\n      exitcode: 0\n      time: 0\n      memory: 128\n      wall-time: 0.079\n      max-rss: 1444\n      status: OK\n      message: \"\"\n      exitsig: 0\n      killed: false\n    task-id: evaluation_test_1\n  - status: OK\n    task-id: fetch_test_solution_1\n  - sandbox_results:\n      exitsig: 0\n      killed: false\n      status: OK\n      memory: 128\n      max-rss: 1320\n      time: 0\n      wall-time: 0.075\n      exitcode: 0\n      message: \"\"\n    task-id: judging_test_1\n    status: OK\n  - task-id: rm_junk_test_1\n    status: OK\n  - status: OK\n    task-id: fetch_test_2\n  - sandbox_results:\n      wall-time: 0.051\n      memory: 128\n      killed: false\n      status: OK\n      time: 0\n      message: \"\"\n      exitcode: 0\n      max-rss: 1444\n      exitsig: 0\n    status: OK\n    task-id: evaluation_test_2\n  - task-id: fetch_test_solution_2\n    status: OK\n  - sandbox_results:\n      wall-time: 0.035\n      message: \"\"\n      exitcode: 0\n      time: 0\n      exitsig: 0\n      killed: false\n      max-rss: 1320\n      memory: 128\n      status: OK\n    task-id: judging_test_2\n    status: OK\n  - task-id: rm_junk_test_2\n    status: OK\n  - task-id: fetch_test_3\n    status: OK\n  - task-id: evaluation_test_3\n    status: OK\n    sandbox_results:\n      max-rss: 1444\n      memory: 128\n      status: OK\n      message: \"\"\n      exitsig: 0\n      killed: false\n      exitcode: 0\n      time: 0\n      wall-time: 0.1\n  - task-id: fetch_test_solution_3\n    status: OK\n  - task-id: judging_test_3\n    status: OK\n    sandbox_results:\n      exitsig: 0\n      killed: false\n      max-rss: 1320\n      status: OK\n      time: 0\n      wall-time: 0.079\n      memory: 128\n      exitcode: 0\n      message: \"\"\n  - task-id: rm_junk_test_3\n    status: OK\n  - task-id: fetch_test_4\n    status: OK\n  - sandbox_results:\n      time: 0.002\n      wall-time: 0.095\n      memory: 252\n      exitsig: 0\n      killed: false\n      max-rss: 1528\n      status: OK\n      message: \"\"\n      exitcode: 0\n    task-id: evaluation_test_4\n    status: OK\n  - status: OK\n    task-id: fetch_test_solution_4\n  - sandbox_results:\n      exitsig: 0\n      killed: false\n      memory: 128\n      max-rss: 1320\n      status: OK\n      message: \"\"\n      exitcode: 0\n      time: 0\n      wall-time: 0.095\n    task-id: judging_test_4\n    status: OK\n  - status: OK\n    task-id: rm_junk_test_4\n  - task-id: fetch_test_5\n    status: OK\n  - status: OK\n    task-id: evaluation_test_5\n    sandbox_results:\n      exitcode: 0\n      time: 0.021\n      wall-time: 0.1\n      max-rss: 2032\n      status: OK\n      exitsig: 0\n      memory: 892\n      killed: false\n      message: \"\"\n  - task-id: fetch_test_solution_5\n    status: OK\n  - task-id: judging_test_5\n    status: OK\n    sandbox_results:\n      exitcode: 0\n      time: 0\n      wall-time: 0.095\n      memory: 128\n      max-rss: 1320\n      status: OK\n      exitsig: 0\n      killed: false\n      message: \"\"\n  - task-id: rm_junk_test_5\n    status: OK\n  - task-id: fetch_test_6\n    status: OK\n  - sandbox_results:\n      message: \"\"\n      max-rss: 4260\n      status: OK\n      exitsig: 0\n      killed: false\n      exitcode: 0\n      wall-time: 0.191\n      memory: 3324\n      time: 0.092\n    task-id: evaluation_test_6\n    status: OK\n  - task-id: fetch_test_solution_6\n    status: OK\n  - sandbox_results:\n      exitsig: 0\n      killed: false\n      message: \"\"\n      exitcode: 0\n      time: 0\n      wall-time: 0.095\n      memory: 128\n      max-rss: 1320\n      status: OK\n    task-id: judging_test_6\n    status: OK\n  - task-id: rm_junk_test_6\n    status: OK',	1,	0,	0);

DROP TABLE IF EXISTS `uploaded_file`;
CREATE TABLE `uploaded_file` (
  `id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `file_path` varchar(200) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `submission_id` varchar(36) DEFAULT NULL,
  KEY `submission_id` (`submission_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `uploaded_file_ibfk_3` FOREIGN KEY (`submission_id`) REFERENCES `submission` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `uploaded_file_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `uploaded_file` (`id`, `name`, `file_path`, `file_size`, `uploaded_at`, `user_id`, `submission_id`) VALUES
('aad6f548-51b1-11e6-a145-180373206d10',	'solution.c',	'C:\\Users\\simon-desktop\\Documents\\Github\\recodex\\api\\uploaded_data/user_1fe2255e-50e2-11e6-beb8-9e71128cae77/solution_5794dbebd4d8c.c',	2433,	'2016-07-24 15:16:59',	'1fe2255e-50e2-11e6-beb8-9e71128cae77',	'c95f9b6a-51bf-11e6-a145-180373206d10');

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` varchar(36) NOT NULL,
  `degrees_before_name` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `degrees_after_name` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `role_id` varchar(50) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `user_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `user` (`id`, `degrees_before_name`, `first_name`, `last_name`, `degrees_after_name`, `email`, `role_id`, `is_verified`) VALUES
('1fe2255e-50e2-11e6-beb8-9e71128cae77',	'Bc.',	'Šimon',	'Rozsíval',	'',	'simon.rozsival@gmail.com',	'student',	1);

-- 2016-07-25 09:56:08