ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';

create table groups
(
  grp     varchar2(80) not null,
  created date not null
)
;
alter table groups
  add constraint pk_groups primary key (GRP);

create table permissions
(
  perm    varchar2(80) not null,
  created date not null
)
;
alter table permissions
  add constraint pk_permissions primary key (PERM);

create table group_permissions
(
  grp     varchar2(80) not null,
  perm    varchar2(80) not null,
  created date not null
)
;
alter table group_permissions
  add constraint pk_group_permissions primary key (GRP, PERM);

create table users
(
  usr          number not null,
  name         varchar2(80) default '',
  mail         varchar2(255) default '',
  cert         number default 0 not null,
  totp         number default 0 not null,
  disabled     number default 0 not null,
  parent       number default null
)
;
alter table users
  add constraint pk_users primary key (USR);

CREATE SEQUENCE users_seq START WITH 1;

CREATE TRIGGER users_bir 
  BEFORE INSERT ON users 
  FOR EACH ROW
  BEGIN
    SELECT users_seq.NEXTVAL
    INTO   :new.usr
    FROM   dual;
  END;
/

create table user_providers
(
  provider     varchar2(80) not null,
  id           varchar2(80) not null,
  usr          number not null,
  name         varchar2(80) default '',
  data         varchar2(255) default '',
  created      date not null,
  used         date default null
)
;
alter table user_providers
  add constraint pk_user_providers primary key (PROVIDER, ID);

create table user_groups
(
  usr         number not null,
  grp         varchar2(80) not null,
  main        number default 0 not null,
  created     date not null
)
;
alter table user_groups
  add constraint pk_user_groups primary key (USR, GRP);


create table log (
  id number not null,
  created date not null,
  lvl varchar2(20) not null CHECK(lvl IN ('emergency','alert','critical','error','warning','notice','info','debug')),
  message varchar2(255) default '',
  context clob default '',
  request clob default '',
  response clob default '',
  ip varchar2(45) default '',
  usr number default null
)
;
alter table log
  add constraint pk_log primary key (ID);

CREATE SEQUENCE log_seq START WITH 1;

CREATE TRIGGER log_bir 
  BEFORE INSERT ON log 
  FOR EACH ROW
  BEGIN
    SELECT log_seq.NEXTVAL
    INTO   :new.id
    FROM   dual;
  END;
/

CREATE TABLE uploads (
  id number not null,
  name varchar2(4000) not null,
  location varchar2(4000) not null,
  bytesize number not null,
  uploaded date not null,
  hash varchar2(32) default '',
  data blob,
  settings clob default ''
)
;
alter table uploads
  add constraint pk_uploads primary key (ID);

CREATE SEQUENCE uploads_seq START WITH 1;

CREATE TRIGGER uploads_bir 
  BEFORE INSERT ON uploads 
  FOR EACH ROW
  BEGIN
    SELECT uploads_seq.NEXTVAL
    INTO   :new.id
    FROM   dual;
  END;
/

INSERT INTO groups (grp, created) VALUES
('Администратори', SYSDATE);

INSERT INTO user_providers (provider, id, usr, name, data, created, used) VALUES
('PasswordDatabase', 'adminuser', 1, '', 'adminpass', SYSDATE, NULL);

INSERT INTO users (usr, name, mail, cert, totp, disabled, parent) VALUES
(1, 'Администратор', 'webdesign@is-bg.net', 0, 0, 0, NULL);

INSERT INTO user_groups (usr, grp, main, created) VALUES
(1, 'Администратори', 1, SYSDATE);

ALTER TABLE group_permissions
  ADD CONSTRAINT FK_GROUPPERMISSIONS_GROUPS FOREIGN KEY (grp) REFERENCES groups (grp) ON DELETE CASCADE;

ALTER TABLE group_permissions
  ADD CONSTRAINT FK_GROUPPERMISSIONS_PERMS FOREIGN KEY (perm) REFERENCES permissions (perm) ON DELETE CASCADE;

ALTER TABLE users
  ADD CONSTRAINT FK_USER_PARENT FOREIGN KEY (parent) REFERENCES users (usr) ON DELETE CASCADE;

ALTER TABLE user_providers
  ADD CONSTRAINT FK_USERPROVIDERS_USERS FOREIGN KEY (usr) REFERENCES users (usr) ON DELETE CASCADE;

ALTER TABLE user_groups
  ADD CONSTRAINT FK_USERGROUPS_GROUPS FOREIGN KEY (grp) REFERENCES groups (grp) ON DELETE CASCADE;

ALTER TABLE user_groups
  ADD CONSTRAINT FK_USERGROUPS_USERS FOREIGN KEY (usr) REFERENCES users (usr) ON DELETE CASCADE;

ALTER TABLE log
  ADD CONSTRAINT FK_LOG_USER FOREIGN KEY (usr) REFERENCES users (usr)  ;
