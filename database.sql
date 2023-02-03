Если сеанс только что стартовал, очистить таблицы urls и url_checks:

TRUNCATE url_checks;
TRUNCATE urls CASCADE;



Если таблиц urls и url_checks не существует, то создать их:

CREATE TABLE urls (id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                                       name varchar(255),
                                       created_at timestamp);
CREATE TABLE url_checks (id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                                             url_id bigint REFERENCES urls (id),
                                             status_code smallint,
                                             h1 varchar(255),
                                             title varchar(255),
                                             description text,
                                             created_at timestamp);
