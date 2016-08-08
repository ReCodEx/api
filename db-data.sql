-- Adminer 4.2.5 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';

USE `recodex-api`;

CREATE TABLE `comment` (
  `id` varchar(60) NOT NULL,
  `comment_thread_id` varchar(60) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `posted_at` timestamp NOT NULL,
  `is_private` tinyint(1) NOT NULL,
  `text` text NOT NULL,
  KEY `comment_thread_id` (`comment_thread_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE `comment_thread` (
  `id` varchar(60) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `exercise` (
  `id` varchar(36) NOT NULL,
  `exercise_id` varchar(36) DEFAULT NULL,
  `name` varchar(36) NOT NULL,
  `version` int(11) NOT NULL,
  `user_id` varchar(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL,
  `description` text NOT NULL,
  `difficulty` enum('trivial','easy','medium','hard','nearly_unsolvable') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `exercise_id` (`exercise_id`),
  CONSTRAINT `exercise_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `exercise_ibfk_2` FOREIGN KEY (`exercise_id`) REFERENCES `exercise` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


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


CREATE TABLE `group_student` (
  `group_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `group_student_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`),
  CONSTRAINT `group_student_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `group_supervisor` (
  `group_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `group_supervisor_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`),
  CONSTRAINT `group_supervisor_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


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


CREATE TABLE `login` (
  `id` varchar(36) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(100) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `login_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


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


CREATE TABLE `resource` (
  `id` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `role` (
  `id` varchar(50) NOT NULL,
  `parent_role_id` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_role_id` (`parent_role_id`),
  CONSTRAINT `role_ibfk_1` FOREIGN KEY (`parent_role_id`) REFERENCES `role` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `submission` (
  `id` varchar(36) NOT NULL,
  `exercise_assignment_id` varchar(36) NOT NULL,
  `submission_evaluation_id` varchar(36) DEFAULT NULL,
  `user_id` varchar(36) NOT NULL,
  `note` varchar(300) NOT NULL,
  `hardware_group` varchar(30) NOT NULL,
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


CREATE TABLE `test_result` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_name` varchar(30) NOT NULL,
  `status` enum('OK','SKIPPED','FAILED') NOT NULL,
  `submission_evaluation_id` varchar(60) NOT NULL,
  `score` float NOT NULL,
  `memory_exceeded` tinyint(1) NOT NULL,
  `used_memory_ratio` float NOT NULL,
  `time_exceeded` tinyint(1) NOT NULL,
  `used_time_ratio` float NOT NULL,
  `exit_code` int(11) NOT NULL,
  `message` text NOT NULL,
  `stats` text NOT NULL,
  `judge_output` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `submission_evaluation_id` (`submission_evaluation_id`),
  CONSTRAINT `test_result_ibfk_1` FOREIGN KEY (`submission_evaluation_id`) REFERENCES `submission_evaluation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


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


CREATE TABLE `user` (
  `id` varchar(36) NOT NULL,
  `degrees_before_name` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `degrees_after_name` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `avatar_url` varchar(200) NOT NULL,
  `role_id` varchar(50) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `is_allowed` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `user_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- 2016-08-08 15:22:29