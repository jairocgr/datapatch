

CREATE TABLE transaction (
  id integer not null auto_increment primary key,
  action varchar(24) not null,
  created_at timestamp not null
);

START TRANSACTION;

INSERT INTO transaction VALUES
  (101, 'action101', '2018-01-01 13:30:35'),
  (102, 'action102', '2018-02-03 13:30:35');

COMMIT;

START TRANSACTION;
INSERT INTO transaction VALUES
  (103, 'action103', '2018-01-01 13:30:35'),
  (104, 'action104', '2018-02-03 13:30:35');
-- cause error
INSERT INTO transaction VALUES
    (105, 'action103', '2018-01-01 13:30:35', 'extra-collumn');
COMMIT;
