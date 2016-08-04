-- Adminer 4.2.5 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE `recodex-api` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `recodex-api`;

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
  `submissions_count_limit` smallint(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `exercise_assignment_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `exercise_assignment` (`id`, `group_id`, `exercise_id`, `name`, `job_config_file_path`, `description`, `first_deadline`, `max_points_before_first_deadline`, `second_deadline`, `max_points_before_second_deadline`, `submissions_count_limit`) VALUES
('25f248c4-50e8-11e6-beb8-9e71128cae77',	'5c38c32a-50e5-11e6-beb8-9e71128cae77',	'ce1f2a4a-50e7-11e6-beb8-9e71128cae77',	'Hrošíci pro dlouhé letní večery',	'C:\\Users\\simon-desktop\\Documents\\Github\\recodex\\api\\exercises\\hrosi-ohradka\\submit\\job-config.yml',	NULL,	'2016-08-04 15:08:30',	10,	'2016-08-31 21:59:59',	7,	10);

DROP TABLE IF EXISTS `group`;
CREATE TABLE `group` (
  `id` varchar(36) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `parent_group_id` varchar(36) DEFAULT NULL,
  `instance_id` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_group_id` (`parent_group_id`),
  KEY `instance_id` (`instance_id`),
  CONSTRAINT `group_ibfk_1` FOREIGN KEY (`parent_group_id`) REFERENCES `group` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `group_ibfk_2` FOREIGN KEY (`instance_id`) REFERENCES `instance` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `group` (`id`, `name`, `description`, `parent_group_id`, `instance_id`) VALUES
('50ebf7ee-50e5-11e6-beb8-9e71128cae77',	'Programování I',	'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec mauris urna, congue ac neque ac, semper ullamcorper est. In eget magna ornare, elementum lectus eu, condimentum est. Vestibulum luctus massa justo, hendrerit consectetur mauris dapibus sed. Integer tristique risus quis diam ultricies commodo sed vestibulum nunc. Phasellus in rhoncus sapien. Curabitur pellentesque sed lectus sed volutpat. Morbi a vulputate orci, vitae gravida elit. In hac habitasse platea dictumst. In rutrum, ex et pellentesque maximus, turpis sapien iaculis tellus, ac placerat est purus eu ex. Cras malesuada justo rhoncus neque tristique porta. Sed vel libero eget ipsum sollicitudin gravida. Integer in commodo turpis. Aliquam sed dictum lectus. Nullam venenatis quis turpis id posuere.\n\nCras sit amet maximus mauris. Donec at tincidunt urna. Aenean vel ipsum at nulla rutrum dictum sit amet non odio. Nulla sagittis, ipsum in imperdiet laoreet, erat nisi laoreet urna, eget volutpat ligula felis vitae nulla. Praesent accumsan fermentum diam at sagittis. Nulla at pellentesque libero. Vivamus sit amet ante neque. Vestibulum dapibus leo quis malesuada volutpat. In pellentesque nec mi eu finibus.',	'de171b8f-ea15-42aa-8b5a-6ff2164beccb',	NULL),
('5c38c32a-50e5-11e6-beb8-9e71128cae77',	'Programování II',	'Nullam dignissim risus non arcu bibendum fermentum. Integer dictum leo porta, fermentum velit nec, viverra ante. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse tempus, enim eu pretium lobortis, dolor lectus maximus nibh, sit amet accumsan nisi dui sit amet dolor. Vivamus tincidunt, ex et vestibulum accumsan, eros augue aliquam nunc, fringilla pretium orci tortor ultricies lectus. Quisque molestie accumsan accumsan. Morbi sit amet enim sed ex bibendum sagittis. Praesent consequat ante a tortor vulputate, eget vestibulum quam ullamcorper. Proin semper mauris augue, nec mattis augue pellentesque ac. Sed at lacus a nibh tempus consectetur.\n\nPhasellus molestie nulla massa, id eleifend urna aliquam vel. In elementum est ac magna ullamcorper, vel aliquam lacus sollicitudin. Aliquam placerat diam quis enim ullamcorper, ac pretium ipsum dignissim. Morbi vitae feugiat leo, vel interdum augue. Phasellus finibus ullamcorper metus, at lobortis odio suscipit sit amet. Aenean vel risus vel lacus faucibus malesuada in at augue. Quisque interdum nisi id felis sodales, sed egestas turpis blandit. Nam convallis nec enim gravida ornare. Vivamus in nulla enim. Etiam tempus faucibus purus, ut convallis ligula porttitor laoreet.\n\nDuis rhoncus pulvinar mi molestie venenatis. Maecenas eget turpis accumsan, sodales leo eget, fermentum nulla. Duis in turpis facilisis, aliquam erat quis, faucibus nulla. Sed tortor libero, euismod in dapibus ut, lacinia eget lorem. Phasellus ornare vel justo at fermentum. Ut mauris eros, malesuada quis metus vitae, vehicula laoreet ex. Integer ac ipsum mattis, sagittis est sit amet, congue odio. Donec in erat quis massa tincidunt vehicula. Nunc sed mauris sit amet dolor consequat placerat. Proin ut turpis in sapien elementum cursus at vel orci.\n\n',	'de171b8f-ea15-42aa-8b5a-6ff2164beccb',	NULL),
('de171b8f-ea15-42aa-8b5a-6ff2164beccb',	'Akademický rok 2016/2017',	'',	NULL,	'91f4882f-30f3-4bc8-87d2-def0e2716c94');

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

DROP TABLE IF EXISTS `login`;
CREATE TABLE `login` (
  `id` varchar(36) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(100) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `login_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `login` (`id`, `username`, `password_hash`, `user_id`) VALUES
('',	'rozsival',	'$2y$11$IIWzr6G15c1d5MrYCXarbOOBEjUZj3RXWFOvaCOENLPqW9BVWvZZG',	'1fe2255e-50e2-11e6-beb8-9e71128cae77'),
('ba77aa8b-5a4f-11e6-9175-180373206d10',	'simon@rozsival.com',	'$2y$11$iNV1hYhAJioBq2.PIukcIORbHuFua9xjYDKE0J0kOLSWjp2B8DXSG',	'ba7644cb-5a4f-11e6-9175-180373206d10');

DROP TABLE IF EXISTS `permission`;
CREATE TABLE `permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` varchar(50) NOT NULL,
  `resource_id` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  KEY `resource_id` (`resource_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `permission` (`id`, `role_id`, `resource_id`, `action`, `is_allowed`) VALUES
(1,	'admin',	'groups',	'view-all',	1);

DROP TABLE IF EXISTS `resource`;
CREATE TABLE `resource` (
  `id` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `resource` (`id`) VALUES
('groups');

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
  `is_allowed` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `user_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `user` (`id`, `degrees_before_name`, `first_name`, `last_name`, `degrees_after_name`, `email`, `role_id`, `is_verified`, `is_allowed`) VALUES
('1fe2255e-50e2-11e6-beb8-9e71128cae77',	'Bc.',	'Šimon',	'Rozsíval',	'',	'simon.rozsival@gmail.com',	'superadmin',	1,	1),
('ba7644cb-5a4f-11e6-9175-180373206d10',	'',	'Simon',	'Rozsival',	'',	'simon@rozsival.com',	'student',	0,	1);

-- 2016-08-04 18:16:48