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
  `description` text,
  `first_deadline` timestamp NOT NULL,
  `second_deadline` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `exercise_assignment_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `exercise_assignment` (`id`, `group_id`, `exercise_id`, `name`, `description`, `first_deadline`, `second_deadline`) VALUES
('25f248c4-50e8-11e6-beb8-9e71128cae77',	'5c38c32a-50e5-11e6-beb8-9e71128cae77',	'ce1f2a4a-50e7-11e6-beb8-9e71128cae77',	'Hrošíci pro dlouhé letní večery',	NULL,	'2016-08-23 15:17:40',	'2016-08-31 21:59:59');

DROP TABLE IF EXISTS `group`;
CREATE TABLE `group` (
  `id` varchar(36) NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `group` (`id`, `name`) VALUES
('50ebf7ee-50e5-11e6-beb8-9e71128cae77',	'Programování I'),
('5c38c32a-50e5-11e6-beb8-9e71128cae77',	'Programování II');

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

DROP TABLE IF EXISTS `submission`;
CREATE TABLE `submission` (
  `id` varchar(36) NOT NULL,
  `exercise_assignment_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `note` varchar(300) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `exercise_assignment_id` (`exercise_assignment_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `submission_ibfk_1` FOREIGN KEY (`exercise_assignment_id`) REFERENCES `exercise_assignment` (`id`),
  CONSTRAINT `submission_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `submission` (`id`, `exercise_assignment_id`, `user_id`, `note`, `submitted_at`) VALUES
('3ef85756-50f6-11e6-beb8-9e71128cae77',	'25f248c4-50e8-11e6-beb8-9e71128cae77',	'1fe2255e-50e2-11e6-beb8-9e71128cae77',	'zkouska',	'2016-07-23 16:55:53');

DROP TABLE IF EXISTS `uploaded_file`;
CREATE TABLE `uploaded_file` (
  `id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `file_path` varchar(200) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL,
  `user_id` varchar(36) NOT NULL,
  KEY `user_id` (`user_id`),
  CONSTRAINT `uploaded_file_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `uploaded_file` (`id`, `name`, `file_path`, `file_size`, `uploaded_at`, `user_id`) VALUES
('083ce531-5103-11e6-a334-180373206d10',	'WP-20160331-20-10-56-Pro.jpg',	'C:\\Users\\simon-desktop\\Documents\\Github\\recodex\\api\\uploaded_data/user_1fe2255e-50e2-11e6-beb8-9e71128cae77/WP-20160331-20-10-56-Pro.jpg_5793b6ee9905f.jpg',	1454942,	'2016-07-23 18:26:54',	'1fe2255e-50e2-11e6-beb8-9e71128cae77'),
('2d84613e-5103-11e6-a334-180373206d10',	'34e60db.jpg',	'C:\\Users\\simon-desktop\\Documents\\Github\\recodex\\api\\uploaded_data/user_1fe2255e-50e2-11e6-beb8-9e71128cae77/34e60db_5793b72d29c56.jpg',	13620,	'2016-07-23 18:27:57',	'1fe2255e-50e2-11e6-beb8-9e71128cae77');

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` varchar(36) NOT NULL,
  `degrees_before_name` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `degrees_after_name` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `user` (`id`, `degrees_before_name`, `first_name`, `last_name`, `degrees_after_name`, `email`, `is_verified`) VALUES
('1fe2255e-50e2-11e6-beb8-9e71128cae77',	'Bc.',	'Šimon',	'Rozsíval',	'',	'simon.rozsival@gmail.com',	1);

-- 2016-07-23 18:32:55