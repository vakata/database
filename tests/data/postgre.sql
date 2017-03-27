CREATE TABLE groups (
  grp varchar(80) NOT NULL,
  created timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO groups (grp, created) VALUES
('Администратори', now()),
('Обикновени', now());

CREATE TABLE permissions (
  perm varchar(80) NOT NULL,
  created timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE group_permissions (
  grp varchar(80) NOT NULL,
  perm varchar(80) NOT NULL,
  created timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
  usr SERIAL NOT NULL,
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
  created timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used timestamptz NULL DEFAULT NULL
);

INSERT INTO user_providers (provider, id, usr, name, data, created, used) VALUES
('PasswordDatabase', 'adminuser', 1, '', 'adminpass', now(), NULL);

CREATE TABLE user_groups (
  usr int NOT NULL,
  grp varchar(80) NOT NULL,
  main smallint NOT NULL,
  created timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO user_groups (usr, grp, main, created) VALUES
(1, 'Администратори', 1, NOW());

CREATE TABLE log (
  id SERIAL NOT NULL,
  created timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lvl varchar(20) NOT NULL DEFAULT 'error',
  message varchar(255) NOT NULL DEFAULT '',
  context text,
  request text,
  response text,
  ip varchar(45) NOT NULL DEFAULT '',
  usr int NULL DEFAULT NULL
);

CREATE TABLE uploads (
  id SERIAL NOT NULL,
  name text NOT NULL,
  location text NOT NULL,
  bytesize bigint NOT NULL DEFAULT '0',
  uploaded timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  hash varchar(32) NOT NULL DEFAULT '',
  data text,
  settings text
);

ALTER TABLE groups
  ADD PRIMARY KEY (grp);

ALTER TABLE group_permissions
  ADD PRIMARY KEY (grp,perm);

ALTER TABLE permissions
  ADD PRIMARY KEY (perm);

ALTER TABLE user_providers
  ADD PRIMARY KEY (provider,id);

ALTER TABLE users
  ADD PRIMARY KEY (usr);

ALTER TABLE user_groups
  ADD PRIMARY KEY (usr,grp);

ALTER TABLE log
  ADD PRIMARY KEY (id);

ALTER TABLE uploads
  ADD PRIMARY KEY (id);


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
