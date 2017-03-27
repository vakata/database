SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS user_groups;
DROP TABLE IF EXISTS user_providers;
DROP TABLE IF EXISTS user_groups;
DROP TABLE IF EXISTS group_permissions;
DROP TABLE IF EXISTS log;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS groups;
DROP TABLE IF EXISTS uploads;
DROP TABLE IF EXISTS permissions;

CREATE TABLE groups (
  grp varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Име на групата',
  created datetime NOT NULL COMMENT 'Дата на създаване на групата'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Потребителски групи';

INSERT INTO groups (grp, created) VALUES
('Администратори', NOW()),
('Обикновени', NOW());

CREATE TABLE permissions (
  perm varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Име на правото',
  created datetime NOT NULL COMMENT 'Дата на добавяне на правото'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Права в системата';

CREATE TABLE group_permissions (
  grp varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Име на групата',
  perm varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Име на правото',
  created datetime NOT NULL COMMENT 'Дата и час на даване на правото на групата'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Права на потребителски групи';

CREATE TABLE users (
  usr int(10) UNSIGNED NOT NULL,
  name varchar(80) NOT NULL DEFAULT '' COMMENT 'Име на потребителя (Иван Георгиев)',
  mail varchar(255) NOT NULL DEFAULT '' COMMENT 'email адрес',
  cert tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Изисква ли се сертификат при вход',
  totp tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Изисква ли се двуфакторна аутентикация при логин',
  disabled tinyint(3) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Блокиран',
  parent int(10) UNSIGNED DEFAULT NULL COMMENT 'master потребител'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Потребители';

INSERT INTO users (usr, name, mail, cert, totp, disabled, parent) VALUES
(1, 'Администратор', 'webdesign@is-bg.net', 0, 0, 0, NULL);

CREATE TABLE user_providers (
  provider varchar(80) NOT NULL COMMENT 'Име на аутентикиращата услуга',
  id varchar(80) NOT NULL COMMENT 'ID на потребителя в аутентикиращата услуга',
  usr int(10) UNSIGNED NOT NULL COMMENT 'ID на потребителя в системата',
  name VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'Кратко име на комбинацията услуга / ID',
  data VARCHAR(255) NULL DEFAULT NULL COMMENT 'Допълнителна информация от услугата',
  created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата и час на създаване',
  used DATETIME NULL DEFAULT NULL COMMENT 'Дата и час на последно използване'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Начини на аутентикация за всеки потребител';

INSERT INTO user_providers (provider, id, usr, name, data, created, used) VALUES
('PasswordDatabase', 'adminuser', 1, '', 'adminpass', NOW(), NULL);

CREATE TABLE user_groups (
  usr int(10) UNSIGNED NOT NULL COMMENT 'ID на потребител',
  grp varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'ID на група',
  main tinyint(1) NOT NULL COMMENT 'Дали е основна група за този потребител (всеки потребител има една основна група)',
  created datetime NOT NULL COMMENT 'Дата и час на добаване на потребителя към групата'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Връзка между потребители и потребителски групи';

INSERT INTO user_groups (usr, grp, main, created) VALUES
(1, 'Администратори', 1, NOW());

CREATE TABLE log (
  id bigint(20) NOT NULL,
  created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата и час на събитието',
  lvl enum('emergency','alert','critical','error','warning','notice','info','debug') NOT NULL DEFAULT 'error' COMMENT 'Тип на събитието',
  message varchar(255) NOT NULL DEFAULT '' COMMENT 'Съобщение',
  context longtext COMMENT 'Допълнителни параметри (в JSON формат)',
  request longtext COMMENT 'Копие на HTTP заявката към системата (ако е налична)',
  response longtext COMMENT 'Копие на HTTP отговора (ако е наличен)',
  ip varchar(45) NOT NULL DEFAULT '' COMMENT 'IP адрес изпратил заявката',
  usr int(10) UNSIGNED DEFAULT NULL COMMENT 'ID на потребител изпратил заявката'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Лог на действията в системата';


CREATE TABLE uploads (
  id bigint(20) UNSIGNED NOT NULL,
  name text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Име на файла',
  location text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Път до файла (спрямо основната директория за прикачени файлове)',
  bytesize bigint(20) NOT NULL DEFAULT '0' COMMENT 'Размер на файла',
  uploaded datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата и час на прикачане в системата',
  hash varchar(32) NOT NULL DEFAULT '' COMMENT 'md5 хеш на файловото съдържание (използва се за ETag)',
  data longblob COMMENT 'Съдържание на файла (само в случай, че не се пише по файловата система)',
  settings text COMMENT 'Опционални настройки на файла'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Прикачени файлове';

ALTER TABLE groups
  ADD PRIMARY KEY (grp);

ALTER TABLE group_permissions
  ADD PRIMARY KEY (grp,perm),
  ADD KEY FK_GROUPPERMISSIONS_PERMISSIONS (perm);

ALTER TABLE permissions
  ADD PRIMARY KEY (perm);

ALTER TABLE user_providers
  ADD PRIMARY KEY (provider,id),
  ADD KEY FK_USERPROVIDERS_USERS (usr);

ALTER TABLE users
  ADD PRIMARY KEY (usr),
  ADD KEY FK_USER_PARENT (parent);

ALTER TABLE user_groups
  ADD PRIMARY KEY (usr,grp),
  ADD KEY FK_USERGROUPS_GROUPS (grp);

ALTER TABLE log
  ADD PRIMARY KEY (id),
  ADD KEY FK_LOG_USER (usr);

ALTER TABLE uploads
  ADD PRIMARY KEY (id);

ALTER TABLE users
  MODIFY usr int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE log
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE uploads
  MODIFY id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE group_permissions
  ADD CONSTRAINT FK_GROUPPERMISSIONS_GROUPS FOREIGN KEY (grp) REFERENCES groups (grp) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT FK_GROUPPERMISSIONS_PERMISSIONS FOREIGN KEY (perm) REFERENCES permissions (perm) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE user_providers
  ADD CONSTRAINT FK_USERPROVIDERS_USERS FOREIGN KEY (usr) REFERENCES users (usr) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE users
  ADD CONSTRAINT FK_USER_PARENT FOREIGN KEY (parent) REFERENCES users (usr) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE user_groups
  ADD CONSTRAINT FK_USERGROUPS_GROUPS FOREIGN KEY (grp) REFERENCES groups (grp) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT FK_USERGROUPS_USERS FOREIGN KEY (usr) REFERENCES users (usr) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE log
  ADD CONSTRAINT FK_LOG_USER FOREIGN KEY (usr) REFERENCES users (usr) ON DELETE NO ACTION ON UPDATE NO ACTION;
