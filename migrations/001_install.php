<?php

namespace Fuel\Migrations;

class Install {
	
	public function up() {
		\DB::query("CREATE TABLE IF NOT EXISTS `doorman_users` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `username` varchar(255) NOT NULL,
		  `password` varchar(255) NOT NULL,
		  `login_hash` varchar(255) NULL,
		  `created_at` DATETIME NULL,
		  `last_login` DATETIME NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY (`username`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1")->execute();

		\DB::query("CREATE TABLE IF NOT EXISTS `doorman_groups` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `name` varchar(255) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1")->execute();

		\DB::query("CREATE TABLE IF NOT EXISTS `doorman_privileges` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `object` varchar(50) NOT NULL,
		  `action` varchar(50) DEFAULT NULL,
		  `group_id` bigint(20) DEFAULT NULL,
		  `user_id` bigint(20) DEFAULT NULL,
		  `object_id` bigint(20) DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  KEY `group_id` (`group_id`),
		  KEY `user_id` (`user_id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1")->execute();

		\DB::query("CREATE TABLE IF NOT EXISTS `doorman_group_assignments` (
		 `user` bigint(20) NOT NULL,
		 `group` bigint(20) NOT NULL,
		 KEY `user` (`user`),
		 KEY `group` (`group`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1")->execute();
	}

	public function down() {
		\DBUtil::drop_table('doorman_users');
		\DBUtil::drop_table('doorman_groups');
		\DBUtil::drop_table('doorman_privileges');
		\DBUtil::drop_table('doorman_group_assignments');
	}
}
