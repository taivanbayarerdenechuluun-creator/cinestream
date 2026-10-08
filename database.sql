-- =====================================================================
-- Movie Streaming Website - Database (Phase 1)
-- Compatible with MariaDB 10.4 / MySQL 5.7+ (XAMPP)
--
-- HOW TO USE: phpMyAdmin -> "Import" tab -> choose this file -> Import.
-- WARNING: re-importing DROPS and recreates all tables (data is lost).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `movie_streaming`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `movie_streaming`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables (children first) so the script can be re-run safely
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `subscriptions`;
DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `ratings`;
DROP TABLE IF EXISTS `watch_history`;
DROP TABLE IF EXISTS `watchlist`;
DROP TABLE IF EXISTS `movie_genres`;
DROP TABLE IF EXISTS `movies`;
DROP TABLE IF EXISTS `genres`;
DROP TABLE IF EXISTS `users`;

-- ---------------------------------------------------------------------
-- users
-- password column stores a password_hash() value (never plain text)
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                VARCHAR(100) NOT NULL,
  `email`               VARCHAR(255) NOT NULL,
  `password`            VARCHAR(255) NOT NULL,
  `role`                ENUM('user','admin') NOT NULL DEFAULT 'user',
  `subscription_status` ENUM('none','active','expired','cancelled') NOT NULL DEFAULT 'none',
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_subscription_status` (`subscription_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- genres
-- ---------------------------------------------------------------------
CREATE TABLE `genres` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_genres_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- movies
-- duration = minutes, rating = 0.0 - 10.0
-- ---------------------------------------------------------------------
CREATE TABLE `movies` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`        VARCHAR(255) NOT NULL,
  `description`  TEXT NOT NULL,
  `release_year` SMALLINT UNSIGNED NOT NULL,
  `duration`     SMALLINT UNSIGNED NOT NULL COMMENT 'Minutes',
  `poster`       VARCHAR(255) DEFAULT NULL,
  `trailer_url`  VARCHAR(500) DEFAULT NULL,
  `video_url`    VARCHAR(500) NOT NULL,
  `rating`       DECIMAL(3,1) NOT NULL DEFAULT 0.0,
  `is_premium`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_movies_title` (`title`),
  KEY `idx_movies_release_year` (`release_year`),
  KEY `idx_movies_is_premium` (`is_premium`),
  KEY `idx_movies_rating` (`rating`),
  FULLTEXT KEY `ft_movies_title_description` (`title`,`description`),
  CONSTRAINT `chk_movies_rating` CHECK (`rating` >= 0 AND `rating` <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- movie_genres (many-to-many link between movies and genres)
-- ---------------------------------------------------------------------
CREATE TABLE `movie_genres` (
  `movie_id` INT UNSIGNED NOT NULL,
  `genre_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`movie_id`,`genre_id`),
  KEY `idx_movie_genres_genre` (`genre_id`),
  CONSTRAINT `fk_movie_genres_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_movie_genres_genre` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- watchlist (a user can save a movie only once)
-- ---------------------------------------------------------------------
CREATE TABLE `watchlist` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `movie_id`   INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_watchlist_user_movie` (`user_id`,`movie_id`),
  KEY `idx_watchlist_movie` (`movie_id`),
  CONSTRAINT `fk_watchlist_user`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_watchlist_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- watch_history (one row per user+movie; progress = seconds watched)
-- ---------------------------------------------------------------------
CREATE TABLE `watch_history` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `movie_id`   INT UNSIGNED NOT NULL,
  `progress`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Seconds watched',
  `watched_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_history_user_movie` (`user_id`,`movie_id`),
  KEY `idx_history_movie` (`movie_id`),
  KEY `idx_history_watched_at` (`watched_at`),
  CONSTRAINT `fk_history_user`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_history_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ratings (one rating per user per movie, 1 - 10)
-- ---------------------------------------------------------------------
CREATE TABLE `ratings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `movie_id`   INT UNSIGNED NOT NULL,
  `rating`     TINYINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ratings_user_movie` (`user_id`,`movie_id`),
  KEY `idx_ratings_movie` (`movie_id`),
  CONSTRAINT `fk_ratings_user`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ratings_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_ratings_range` CHECK (`rating` >= 1 AND `rating` <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- comments (user-generated: ALWAYS escape with htmlspecialchars on output)
-- ---------------------------------------------------------------------
CREATE TABLE `comments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `movie_id`   INT UNSIGNED NOT NULL,
  `comment`    TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comments_user` (`user_id`),
  KEY `idx_comments_movie_created` (`movie_id`,`created_at`),
  CONSTRAINT `fk_comments_user`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- subscriptions
-- ---------------------------------------------------------------------
CREATE TABLE `subscriptions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `plan`       ENUM('basic','standard','premium') NOT NULL,
  `price`      DECIMAL(8,2) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date`   DATE NOT NULL,
  `status`     ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subscriptions_user` (`user_id`),
  KEY `idx_subscriptions_status_end` (`status`,`end_date`),
  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_subscriptions_dates` CHECK (`end_date` >= `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- payments (kept even if a subscription row is removed -> SET NULL)
-- ---------------------------------------------------------------------
CREATE TABLE `payments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `subscription_id` INT UNSIGNED DEFAULT NULL,
  `amount`          DECIMAL(8,2) NOT NULL,
  `payment_method`  VARCHAR(50) NOT NULL,
  `transaction_id`  VARCHAR(100) NOT NULL,
  `status`          ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `paid_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_transaction` (`transaction_id`),
  KEY `idx_payments_user` (`user_id`),
  KEY `idx_payments_subscription` (`subscription_id`),
  KEY `idx_payments_status` (`status`),
  CONSTRAINT `fk_payments_user`         FOREIGN KEY (`user_id`)         REFERENCES `users` (`id`)         ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DEMO DATA
-- (Demo users are created by setup_demo_users.php so that passwords are
--  hashed with PHP's password_hash() - no plain-text passwords here.)
-- =====================================================================

INSERT INTO `genres` (`id`, `name`) VALUES
  (1,  'Action'),
  (2,  'Comedy'),
  (3,  'Drama'),
  (4,  'Horror'),
  (5,  'Romance'),
  (6,  'Sci-Fi'),
  (7,  'Animation'),
  (8,  'Adventure'),
  (9,  'Thriller'),
  (10, 'Documentary');

-- All movies below are fictional demo entries.
-- video_url / trailer_url point to small public sample videos (placeholders).
-- poster uses a filename inside assets/images/ (add your own placeholder.jpg).
INSERT INTO `movies`
  (`id`, `title`, `description`, `release_year`, `duration`, `poster`, `trailer_url`, `video_url`, `rating`, `is_premium`)
VALUES
  (1, 'Neon Horizon',
   'In a rain-soaked megacity, a courier discovers a data chip that could shut down the city''s AI overseer.',
   2024, 118, 'placeholder.jpg',
   'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
   'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
   7.8, 0),

  (2, 'The Last Lighthouse',
   'A retired keeper and a stranded sailor uncover a decades-old secret during a week-long storm.',
   2022, 104, 'placeholder.jpg',
   'https://www.w3schools.com/html/mov_bbb.mp4',
   'https://www.w3schools.com/html/mov_bbb.mp4',
   7.2, 0),

  (3, 'Laugh Track',
   'A struggling stand-up comedian accidentally becomes the voice of a viral cooking show.',
   2023, 96, 'placeholder.jpg',
   'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
   'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
   6.9, 0),

  (4, 'Midnight Corridor',
   'A night-shift nurse realises the hospital''s east wing only exists after 2 a.m.',
   2021, 101, 'placeholder.jpg',
   'https://www.w3schools.com/html/mov_bbb.mp4',
   'https://www.w3schools.com/html/mov_bbb.mp4',
   6.5, 1),

  (5, 'Paper Hearts',
   'Two rival bookshop owners exchange anonymous letters without knowing who is on the other side.',
   2020, 112, 'placeholder.jpg',
   'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
   'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
   7.0, 0),

  (6, 'Stardust Run',
   'A smuggler crew races across the outer colonies to deliver a crate that nobody is allowed to open.',
   2025, 130, 'placeholder.jpg',
   'https://www.w3schools.com/html/mov_bbb.mp4',
   'https://www.w3schools.com/html/mov_bbb.mp4',
   8.1, 1),

  (7, 'Little Cloud',
   'A tiny cloud who cannot make rain sets off on a journey across the sky to find his purpose.',
   2023, 88, 'placeholder.jpg',
   'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
   'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
   7.6, 0),

  (8, 'Summit Fever',
   'Five climbers race a closing weather window to reach an unclimbed peak.',
   2019, 125, 'placeholder.jpg',
   'https://www.w3schools.com/html/mov_bbb.mp4',
   'https://www.w3schools.com/html/mov_bbb.mp4',
   7.4, 1),

  (9, 'Silent Signal',
   'A radio astronomer receives a repeating message that seems to be addressed to her by name.',
   2022, 109, 'placeholder.jpg',
   'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
   'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
   7.1, 1),

  (10, 'Rivers of the World',
   'A documentary following five great rivers from their glacial sources to the sea.',
   2021, 92, 'placeholder.jpg',
   'https://www.w3schools.com/html/mov_bbb.mp4',
   'https://www.w3schools.com/html/mov_bbb.mp4',
   8.0, 0);

-- Genre links: (movie_id, genre_id)
-- 1 Action  2 Comedy  3 Drama  4 Horror  5 Romance
-- 6 Sci-Fi  7 Animation  8 Adventure  9 Thriller  10 Documentary
INSERT INTO `movie_genres` (`movie_id`, `genre_id`) VALUES
  (1, 6), (1, 1),
  (2, 3), (2, 9),
  (3, 2),
  (4, 4), (4, 9),
  (5, 5), (5, 3),
  (6, 6), (6, 8),
  (7, 7), (7, 8), (7, 2),
  (8, 8), (8, 1),
  (9, 9), (9, 6),
  (10, 10);
