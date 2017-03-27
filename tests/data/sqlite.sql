CREATE TABLE groups (
  grp varchar(80) PRIMARY KEY NOT NULL,
  created text NOT NULL DEFAULT CURRENT_TIMESTAMP
) WITHOUT ROWID;

CREATE TABLE permissions (
  perm varchar(80) PRIMARY KEY NOT NULL,
  created text NOT NULL DEFAULT CURRENT_TIMESTAMP
) WITHOUT ROWID;

INSERT INTO groups (grp, created) VALUES
('Администратори', CURRENT_TIMESTAMP),
('Обикновени', CURRENT_TIMESTAMP);

CREATE TABLE group_permissions (
  grp varchar(80) NOT NULL,
  perm varchar(80) NOT NULL,
  created text NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (grp, perm),
  FOREIGN KEY (grp) REFERENCES groups(grp) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (perm) REFERENCES permissions(perm) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE users (
  usr int PRIMARY KEY AUTOINCREMENT NOT NULL,
  name varchar(80) NOT NULL DEFAULT '',
  mail varchar(255) NOT NULL DEFAULT '',
  cert smallint NOT NULL DEFAULT '0',
  totp smallint NOT NULL DEFAULT '0',
  disabled smallint NOT NULL DEFAULT '0',
  parent int DEFAULT NULL
);

INSERT INTO users (usr, name, mail, cert, totp, disabled, parent) VALUES
(1, 'Администратор', 'webdesign@is-bg.net', 0, 0, 0, NULL);

CREATE TABLE user_providers (
  provider varchar(80) NOT NULL,
  id varchar(80) NOT NULL,
  usr int NOT NULL,
  name VARCHAR(80) NOT NULL DEFAULT '',
  data VARCHAR(255) NULL DEFAULT NULL,
  created text NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used text NULL DEFAULT NULL,
  PRIMARY KEY (provider, id),
  FOREIGN KEY (usr) REFERENCES users(usr) ON DELETE CASCADE ON UPDATE CASCADE
);

INSERT INTO user_providers (provider, id, usr, name, data, created, used) VALUES
('PasswordDatabase', 'adminuser', 1, '', 'adminpass', CURRENT_TIMESTAMP, NULL);

CREATE TABLE user_groups (
  usr int PRIMARY KEY NOT NULL,
  grp varchar(80)  PRIMARY KEY NOT NULL,
  main smallint NOT NULL,
  created text NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usr, grp),
  FOREIGN KEY (usr) REFERENCES users(usr) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (grp) REFERENCES groups(grp) ON DELETE CASCADE ON UPDATE CASCADE
);

INSERT INTO user_groups (usr, grp, main, created) VALUES
(1, 'Администратори', 1, CURRENT_TIMESTAMP);

CREATE TABLE log (
  id int PRIMARY KEY AUTOINCREMENT NOT NULL,
  created timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lvl varchar(20) NOT NULL DEFAULT 'error',
  message varchar(255) NOT NULL DEFAULT '',
  context text,
  request text,
  response text,
  ip varchar(45) NOT NULL DEFAULT '',
  usr int NULL DEFAULT NULL,
  FOREIGN KEY (usr) REFERENCES users(usr) ON DELETE NO ACTION ON UPDATE NO ACTION
);


CREATE TABLE uploads (
  id int PRIMARY KEY AUTOINCREMENT NOT NULL,
  name text NOT NULL,
  location text NOT NULL,
  bytesize bigint NOT NULL DEFAULT '0',
  uploaded timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  hash varchar(32) NOT NULL DEFAULT '',
  data text,
  settings text
);
