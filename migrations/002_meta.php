<?php

namespace Fuel\Migrations;

class Meta {
	public function up() {
		\DB::query("CREATE TABLE IF NOT EXISTS `doorman_user_meta` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `user_id` bigint(20) NOT NULL,
		  `key` varchar(100) NOT NULL,
		  `value` text DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  KEY (`user_id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1")->execute();
	}

	public function down() {
		\DBUtil::drop_table('doorman_user_meta');
	}
}