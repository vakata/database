DROP TABLE IF EXISTS book_tag;
DROP TABLE IF EXISTS tag;
DROP TABLE IF EXISTS book;
DROP TABLE IF EXISTS author;

CREATE TABLE IF NOT EXISTS author (
  id SERIAL NOT NULL,
  name varchar(255) NOT NULL,
  PRIMARY KEY (id)
);

INSERT INTO author (name) VALUES
	('Terry Pratchett'),
	('Ray Bradburry'),
	('Douglas Adams');

CREATE TABLE IF NOT EXISTS tag (
  id SERIAL NOT NULL,
  name varchar(255) NOT NULL,
  PRIMARY KEY (id)
);

INSERT INTO tag (name) VALUES
    ('Discworld'),
    ('Escarina'),
    ('Cooking');

CREATE TABLE IF NOT EXISTS book (
  id SERIAL NOT NULL,
  name varchar(255) NOT NULL,
  author_id int NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT BOOK_AUTHOR FOREIGN KEY (author_id) REFERENCES author (id)
) ;

INSERT INTO book (name, author_id) VALUES
	('Equal rites', 1);

CREATE TABLE IF NOT EXISTS book_tag (
  book_id int NOT NULL,
  tag_id int NOT NULL,
  PRIMARY KEY (book_id,tag_id),
  CONSTRAINT TAG_BOOK FOREIGN KEY (book_id) REFERENCES book (id),
  CONSTRAINT TAG_TAG FOREIGN KEY (tag_id) REFERENCES tag (id)
) ;

INSERT INTO book_tag (book_id, tag_id) VALUES
	(1, 1),
	(1, 2);
