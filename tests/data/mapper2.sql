DROP TABLE IF EXISTS driver_cars;
DROP TABLE IF EXISTS car_pictures;
DROP TABLE IF EXISTS race_participants;
DROP TABLE IF EXISTS races;
DROP TABLE IF EXISTS drivers;
DROP TABLE IF EXISTS cars;
DROP TABLE IF EXISTS avatars;
DROP TABLE IF EXISTS pictures;

CREATE TABLE IF NOT EXISTS avatars (
  avatar SERIAL NOT NULL,
  url varchar(255) NOT NULL,
  PRIMARY KEY (avatar)
);
CREATE TABLE IF NOT EXISTS drivers (
  driver SERIAL NOT NULL,
  name varchar(255) NOT NULL,
  avatar int DEFAULT NULL,
  PRIMARY KEY (driver),
  CONSTRAINT DRIVER_AVATAR FOREIGN KEY (avatar) REFERENCES avatars (avatar)
);
CREATE TABLE IF NOT EXISTS cars (
  car SERIAL NOT NULL,
  name varchar(255) NOT NULL,
  PRIMARY KEY (car)
);
CREATE TABLE IF NOT EXISTS driver_cars (
  driver int NOT NULL,
  car int NOT NULL,
  PRIMARY KEY (driver,car),
  CONSTRAINT CD_DRIVER FOREIGN KEY (driver) REFERENCES drivers (driver),
  CONSTRAINT CD_CAR FOREIGN KEY (car) REFERENCES cars (car)
);

CREATE TABLE IF NOT EXISTS races (
    race SERIAL NOT NULL,
    name varchar(255) NOT NULL,
    PRIMARY KEY (race)
);
CREATE TABLE IF NOT EXISTS race_participants (
  participant SERIAL NOT NULL,
  race int NOT NULL,
  driver int,
  car int,
  position int DEFAULT NULL,
  PRIMARY KEY (participant),
  CONSTRAINT RACEP_RACE FOREIGN KEY (race) REFERENCES races (race),
  CONSTRAINT RACEP_DRIVER FOREIGN KEY (driver) REFERENCES drivers (driver),
  CONSTRAINT RACEP_CAR FOREIGN KEY (car) REFERENCES cars (car)
);
CREATE TABLE IF NOT EXISTS pictures (
  picture SERIAL NOT NULL,
  url varchar(255) NOT NULL,
  PRIMARY KEY (picture)
);
CREATE TABLE IF NOT EXISTS car_pictures (
  car int NOT NULL,
  picture int NOT NULL,
  pos int NOT NULL,
  PRIMARY KEY (picture,car,pos),
  CONSTRAINT CARP_CAR FOREIGN KEY (car) REFERENCES cars (car),
  CONSTRAINT CARP_PIC FOREIGN KEY (picture) REFERENCES pictures (picture)
);

INSERT INTO avatars (url) VALUES ('avatar');
INSERT INTO pictures (url) VALUES ('picture1');
INSERT INTO pictures (url) VALUES ('picture2');

INSERT INTO drivers (name) VALUES ('driver1');
INSERT INTO drivers (name, avatar) VALUES ('driver2', 1);
INSERT INTO drivers (name, avatar) VALUES ('driver3', 1);

INSERT INTO cars (name) VALUES ('car1');
INSERT INTO cars (name) VALUES ('car2');
INSERT INTO cars (name) VALUES ('car3');

INSERT INTO car_pictures VALUES (3,1,1);
INSERT INTO car_pictures VALUES (3,2,2);
INSERT INTO car_pictures VALUES (2,2,1);

INSERT INTO driver_cars (driver, car) VALUES (1,1);
INSERT INTO driver_cars (driver, car) VALUES (2,1);
INSERT INTO driver_cars (driver, car) VALUES (2,2);
INSERT INTO driver_cars (driver, car) VALUES (3,3);

INSERT INTO races (name) VALUES ('race1');
INSERT INTO races (name) VALUES ('race2');

INSERT INTO race_participants (race, driver, car) VALUES (1,1,1);
INSERT INTO race_participants (race, driver, car) VALUES (1,2,2);
INSERT INTO race_participants (race, driver, car) VALUES (1,3,2);
INSERT INTO race_participants (race, driver, car) VALUES (2,2,1);
INSERT INTO race_participants (race, driver, car) VALUES (2,3,2);

DROP TABLE IF EXISTS answers;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS polls;
DROP TABLE IF EXISTS pgrps;

CREATE TABLE IF NOT EXISTS pgrps (
    grp SERIAL NOT NULL,
    name varchar(255) NOT NULL,
    PRIMARY KEY (grp)
);
CREATE TABLE IF NOT EXISTS polls (
    poll SERIAL NOT NULL,
    grp int NOT NULL,
    name varchar(255) NOT NULL,
    PRIMARY KEY (poll),
    CONSTRAINT POLL_GRP FOREIGN KEY (grp) REFERENCES pgrps (grp)
);
CREATE TABLE IF NOT EXISTS questions (
    question SERIAL NOT NULL,
    poll int NOT NULL,
    name varchar(255) NOT NULL,
    PRIMARY KEY (question),
    CONSTRAINT Q_POLL FOREIGN KEY (poll) REFERENCES polls (poll)
);
CREATE TABLE IF NOT EXISTS answers (
    answer SERIAL NOT NULL,
    question int NOT NULL,
    name varchar(255) NOT NULL,
    PRIMARY KEY (answer),
    CONSTRAINT A_Q FOREIGN KEY (question) REFERENCES questions (question)
);
INSERT INTO pgrps (name) VALUES ('G');
INSERT INTO polls (grp,name) VALUES (1, 'P');
INSERT INTO questions (poll,name) VALUES (1, 'Q');
INSERT INTO answers (question,name) VALUES (1, 'A');
