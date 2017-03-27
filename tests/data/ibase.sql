CREATE TABLE groups (
  grp varchar(80) NOT NULL,
  created timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE permissions (
  perm varchar(80) NOT NULL,
  created timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE group_permissions (
  grp varchar(80) NOT NULL,
  perm varchar(80) NOT NULL,
  created timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE users (
  usr int NOT NULL,
  name varchar(80) DEFAULT '' NOT NULL,
  mail varchar(255) DEFAULT '' NOT NULL,
  cert smallint DEFAULT '0' NOT NULL,
  totp smallint DEFAULT '0' NOT NULL,
  disabled smallint DEFAULT '0' NOT NULL,
  parent int DEFAULT NULL
);

CREATE TABLE user_providers (
  provider varchar(80) NOT NULL,
  id varchar(80) NOT NULL,
  usr int NOT NULL,
  name VARCHAR(80) DEFAULT '' NOT NULL,
  data VARCHAR(255) DEFAULT NULL,
  created timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
  used timestamp DEFAULT NULL
);

CREATE GENERATOR GEN_USERS_USR;
SET GENERATOR GEN_USERS_USR TO 0;

set term !! ;
CREATE TRIGGER USERS_BI FOR USERS
ACTIVE BEFORE INSERT POSITION 0
AS
BEGIN
if (NEW.USR is NULL) then NEW.USR = GEN_ID(GEN_USERS_USR, 1);
END!!
set term ; !!

CREATE TABLE user_groups (
  usr int NOT NULL,
  grp varchar(80) NOT NULL,
  main smallint NOT NULL,
  created timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE log (
  id bigint NOT NULL,
  created timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
  lvl varchar(20) DEFAULT 'error' NOT NULL,
  message varchar(255) DEFAULT '' NOT NULL,
  context BLOB SUB_TYPE TEXT,
  request BLOB SUB_TYPE TEXT,
  response BLOB SUB_TYPE TEXT,
  ip varchar(45) DEFAULT '' NOT NULL,
  usr int DEFAULT NULL
);

CREATE GENERATOR GEN_LOG_ID;
SET GENERATOR GEN_LOG_ID TO 0;

set term !! ;
CREATE TRIGGER LOG_BI FOR LOG
ACTIVE BEFORE INSERT POSITION 0
AS
BEGIN
if (NEW.ID is NULL) then NEW.ID = GEN_ID(GEN_LOG_ID, 1);
END!!
set term ; !!

CREATE TABLE uploads (
  id bigint NOT NULL,
  name BLOB SUB_TYPE TEXT NOT NULL,
  location BLOB SUB_TYPE TEXT NOT NULL,
  bytesize bigint DEFAULT '0' NOT NULL,
  uploaded timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
  hash varchar(32) DEFAULT '' NOT NULL,
  data BLOB SUB_TYPE TEXT,
  settings BLOB SUB_TYPE TEXT
);

CREATE GENERATOR GEN_UPLOADS_ID;
SET GENERATOR GEN_UPLOADS_ID TO 0;

set term !! ;
CREATE TRIGGER UPLOADS_BI FOR UPLOADS
ACTIVE BEFORE INSERT POSITION 0
AS
BEGIN
if (NEW.ID is NULL) then NEW.ID = GEN_ID(GEN_UPLOADS_ID, 1);
END!!
set term ; !!

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


INSERT INTO groups (grp, created) VALUES
('Администратори', CURRENT_TIMESTAMP);
INSERT INTO groups (grp, created) VALUES
('Обикновени', CURRENT_TIMESTAMP);
INSERT INTO users (usr, name, mail, cert, totp, disabled, parent) VALUES
(1, 'Администратор', 'webdesign@is-bg.net', 0, 0, 0, NULL);
INSERT INTO user_providers (provider, id, usr, name, data, created, used) VALUES
('PasswordDatabase', 'adminuser', 1, '', 'adminpass', CURRENT_TIMESTAMP, NULL);
INSERT INTO user_groups (usr, grp, main, created) VALUES
(1, 'Администратори', 1, CURRENT_TIMESTAMP);