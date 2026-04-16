CREATE TABLE IF NOT EXISTS `card` (
  `card_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `card_type` VARCHAR(16) NOT NULL,
  `card_type_arg` INT NOT NULL,
  `card_location` VARCHAR(32) NOT NULL,
  `card_location_arg` INT NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `rnt_role` (
  `player_id` INT(10) UNSIGNED NOT NULL,
  `role` VARCHAR(16) NOT NULL,
  PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rnt_seen_indice` (
  `player_id` INT(10) UNSIGNED NOT NULL,
  `card_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`player_id`, `card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rnt_seen_role` (
  `viewer_id` INT(10) UNSIGNED NOT NULL,
  `target_id` INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`viewer_id`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
