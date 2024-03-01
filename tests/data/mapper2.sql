DROP TABLE IF EXISTS driver_cars;
DROP TABLE IF EXISTS race_participants;
DROP TABLE IF EXISTS races;
DROP TABLE IF EXISTS drivers;
DROP TABLE IF EXISTS cars;
DROP TABLE IF EXISTS avatars;

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
  driver int NOT NULL,
  car int NOT NULL,
  position int DEFAULT NULL,
  PRIMARY KEY (participant),
  CONSTRAINT RACEP_RACE FOREIGN KEY (race) REFERENCES races (race),
  CONSTRAINT RACEP_DRIVER FOREIGN KEY (driver) REFERENCES drivers (driver),
  CONSTRAINT RACEP_CAR FOREIGN KEY (car) REFERENCES cars (car)
);

INSERT INTO avatars (url) VALUES ('avatar');

INSERT INTO drivers (name) VALUES ('driver1');
INSERT INTO drivers (name, avatar) VALUES ('driver2', 1);
INSERT INTO drivers (name, avatar) VALUES ('driver3', 1);

INSERT INTO cars (name) VALUES ('car1');
INSERT INTO cars (name) VALUES ('car2');
INSERT INTO cars (name) VALUES ('car3');

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
