--
-- Table structure for table `course_team`
--
DROP TABLE IF EXISTS `course_team`;
CREATE TABLE `course_team` (
  `team_id` int(11) NOT NULL AUTO_INCREMENT,
  `term` varchar(255) DEFAULT '',
  `course_name` varchar(255) DEFAULT '',
  `teacher_name` varchar(255) DEFAULT '',
  `class_week_time` varchar(255) DEFAULT '',
  `class_id_in_school` varchar(255) DEFAULT '',
  PRIMARY KEY (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `course_team_relation`
--
DROP TABLE IF EXISTS `course_team_relation`;
CREATE TABLE `course_team_relation` (
  `relation_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(48) NOT NULL,
  `team_id` int(11) NOT NULL,
  PRIMARY KEY (`relation_id`),
  KEY `user_id` (`user_id`),
  KEY `team_id` (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `privilege_distribution` ADD COLUMN `manage_course_team` tinyint(4) DEFAULT 0;

UPDATE `privilege_distribution` SET `manage_course_team` = 1 WHERE `group_name` = 'administrator';
UPDATE `privilege_distribution` SET `manage_course_team` = 1 WHERE `group_name` = 'teacher';
UPDATE `privilege_distribution` SET `manage_course_team` = 0 WHERE `group_name` = 'exam_user';
UPDATE `privilege_distribution` SET `manage_course_team` = 0 WHERE `group_name` = 'hznu_viewer';
UPDATE `privilege_distribution` SET `manage_course_team` = 1 WHERE `group_name` = 'root';
UPDATE `privilege_distribution` SET `manage_course_team` = 0 WHERE `group_name` = 'source_browser';
UPDATE `privilege_distribution` SET `manage_course_team` = 0 WHERE `group_name` = 'teacher_assistant';

ALTER TABLE `problem`
ADD COLUMN `problem_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0=normal,1=prompt';

ALTER TABLE `problem`
ADD COLUMN `standard_length` int(11) NOT NULL DEFAULT '200' COMMENT 'prompt judge standard length';

CREATE TABLE IF NOT EXISTS `prompt_submission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `solution_id` int(11) DEFAULT NULL,
  `problem_id` int(11) NOT NULL,
  `user_id` varchar(48) NOT NULL,
  `contest_id` int(11) DEFAULT NULL,
  `prompt` text NOT NULL,
  `prompt_length` int(11) NOT NULL,
  `generated_code` mediumtext,
  `deepseek_status` varchar(32) NOT NULL DEFAULT 'PENDING',
  `deepseek_error` text,
  `model_name` varchar(128) DEFAULT NULL,
  `score` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `solution_id` (`solution_id`),
  KEY `problem_id` (`problem_id`),
  KEY `user_id` (`user_id`),
  KEY `contest_id` (`contest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Add a local test account without changing login/register logic.
INSERT INTO `users` (`user_id`,`email`,`ip`,`accesstime`,`password`,`reg_time`,`nick`,`school`)
SELECT 'test','test@temp.com','::1',NOW(),'cX954biYFEtBFI90OoC0FF7QwbdmM2M5',NOW(),'test',''
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `user_id`='test');

INSERT INTO `users_cache` (`user_id`,`class`,`AC_day`,`sub_day`,`activity`,`total_score`)
SELECT 'test','',0000000001,0000000001,NULL,NULL
WHERE NOT EXISTS (SELECT 1 FROM `users_cache` WHERE `user_id`='test');
