create table authgroup
(
   authgroup_id                   bigint                         not null auto_increment,
   groupname                      varchar(255)                   not null,
   comment                        text,
   primary key (authgroup_id)
)
type = innodb;
create unique index groupname_uk on authgroup ( groupname );

create table authgroup_authright
(
   authgroup                      bigint                         not null,
   authright                      bigint                         not null,
   primary key (authgroup, authright)
)
type = innodb;
create index authgroup_fk on authgroup_authright ( authgroup );
create index authright_fk on authgroup_authright ( authright );

create table authright
(
   authright_id                   bigint                         not null auto_increment,
   rightname                      varchar(255)                   not null,
   comment                        text,
   sequence                       int,
   primary key (authright_id)
)
type = innodb;
create unique index rightname_uk on authright ( rightname );

create table authuser
(
   authuser_id                    bigint                         not null auto_increment,
   username                       varchar(255)                   not null,
   password                       varchar(255),
   firstname                      varchar(255),
   surname                        varchar(255),
   comment                        text,
   disabled                       enum('Y','N'),          
   primary key (authuser_id)
)
type = innodb;
create unique index username_uk on authuser ( username );

create table authuser_authgroup (
   authuser                       bigint                         not null,
   authgroup                      bigint                         not null,
   primary key (authuser, authgroup)
)
type = innodb;
create index authgroup_fk on authuser_authgroup ( authgroup );
create index authuser_fk on authuser_authgroup ( authuser );

create table authuser_authright
(
   authuser                       bigint                         not null,
   authright                      bigint                         not null,
   primary key (authuser, authright)
)
type = innodb;
create index authuser_fk on authuser_authright ( authuser );
create index authright_fk on authuser_authright ( authright );

alter table authgroup_authright add constraint fk_authgroup_authright__authgroup foreign key (authgroup)
      references authgroup (authgroup_id) on delete restrict on update restrict;

alter table authgroup_authright add constraint fk_authgroup_authright__authright foreign key (authright)
      references authright (authright_id) on delete restrict on update restrict;

alter table authuser_authgroup add constraint fk_authuser_authgroup__authgroup foreign key (authgroup)
      references authgroup (authgroup_id) on delete restrict on update restrict;

alter table authuser_authgroup add constraint fk_authuser_authgroup__authuser foreign key (authuser)
      references authuser (authuser_id) on delete restrict on update restrict;

alter table authuser_authright add constraint fk_authuser_authright__authright foreign key (authright)
      references authright (authright_id) on delete restrict on update restrict;

alter table authuser_authright add constraint fk_authuser_authright__authuser foreign key (authuser)
      references authuser (authuser_id) on delete restrict on update restrict;

