SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


CREATE TABLE IF NOT EXISTS addon (
  id int(11) NOT NULL AUTO_INCREMENT,
  `name` char(255) NOT NULL,
  version char(255) NOT NULL,
  installed tinyint(1) NOT NULL DEFAULT '0',
  `timestamp` bigint(20) NOT NULL DEFAULT '0',
  plugin_admin tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS attach (
  id int(11) NOT NULL AUTO_INCREMENT,
  uid int(11) NOT NULL,
  `hash` char(64) NOT NULL,
  filename char(255) NOT NULL,
  filetype char(64) NOT NULL,
  filesize int(11) NOT NULL,
  `data` longblob NOT NULL,
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  edited datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  allow_cid mediumtext NOT NULL,
  allow_gid mediumtext NOT NULL,
  deny_cid mediumtext NOT NULL,
  deny_gid mediumtext NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS auth_codes (
  id varchar(40) NOT NULL,
  client_id varchar(20) NOT NULL,
  redirect_uri varchar(200) NOT NULL,
  expires int(11) NOT NULL,
  scope varchar(250) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `cache` (
  k char(255) NOT NULL,
  v text NOT NULL,
  updated datetime NOT NULL,
  PRIMARY KEY (k)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS challenge (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  challenge char(255) NOT NULL,
  zid char(255) NOT NULL,
  expire int(11) NOT NULL,
  `type` char(255) NOT NULL,
  last_update char(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS clients (
  client_id varchar(20) NOT NULL,
  pw varchar(20) NOT NULL,
  redirect_uri varchar(200) NOT NULL,
  PRIMARY KEY (client_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS config (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  cat char(255) NOT NULL,
  k char(255) NOT NULL,
  v text NOT NULL,
  PRIMARY KEY (id),
  KEY cat (cat),
  KEY k (k)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS contact (
  id int(11) NOT NULL AUTO_INCREMENT,
  uid int(11) NOT NULL COMMENT 'owner uid',
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  self tinyint(1) NOT NULL DEFAULT '0',
  remote_self tinyint(1) NOT NULL DEFAULT '0',
  rel tinyint(1) NOT NULL DEFAULT '0',
  duplex tinyint(1) NOT NULL DEFAULT '0',
  network char(255) NOT NULL,
  `name` char(255) NOT NULL,
  nick char(255) NOT NULL,
  photo text NOT NULL,
  thumb text NOT NULL,
  micro text NOT NULL,
  site_pubkey text NOT NULL,
  issued_id char(255) NOT NULL,
  dfrn_id char(255) NOT NULL,
  url char(255) NOT NULL,
  addr char(255) NOT NULL,
  alias char(255) NOT NULL,
  pubkey text NOT NULL,
  prvkey text NOT NULL,
  request text NOT NULL,
  notify text NOT NULL,
  poll text NOT NULL,
  confirm text NOT NULL,
  aes_allow tinyint(1) NOT NULL DEFAULT '0',
  `ret-aes` tinyint(1) NOT NULL DEFAULT '0',
  usehub tinyint(1) NOT NULL DEFAULT '0',
  subhub tinyint(1) NOT NULL DEFAULT '0',
  hub_verify char(255) NOT NULL,
  last_update datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  success_update datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  name_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  uri_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  avatar_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  term_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  priority tinyint(3) NOT NULL,
  blocked tinyint(1) NOT NULL DEFAULT '1',
  readonly tinyint(1) NOT NULL DEFAULT '0',
  writable tinyint(1) NOT NULL DEFAULT '0',
  pending tinyint(1) NOT NULL DEFAULT '1',
  rating tinyint(1) NOT NULL DEFAULT '0',
  reason text NOT NULL,
  info mediumtext NOT NULL,
  profile_id int(11) NOT NULL DEFAULT '0',
  bdyear char(4) NOT NULL COMMENT 'birthday notify flag',
  PRIMARY KEY (id),
  KEY uid (uid),
  KEY self (self),
  KEY `issued-id` (issued_id),
  KEY `dfrn-id` (dfrn_id),
  KEY blocked (blocked),
  KEY readonly (readonly)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `event` (
  id int(11) NOT NULL AUTO_INCREMENT,
  uid int(11) NOT NULL,
  cid int(11) NOT NULL,
  uri char(255) NOT NULL,
  created datetime NOT NULL,
  edited datetime NOT NULL,
  `start` datetime NOT NULL,
  finish datetime NOT NULL,
  `desc` text NOT NULL,
  location text NOT NULL,
  `type` char(255) NOT NULL,
  nofinish tinyint(1) NOT NULL DEFAULT '0',
  adjust tinyint(1) NOT NULL DEFAULT '1',
  allow_cid mediumtext NOT NULL,
  allow_gid mediumtext NOT NULL,
  deny_cid mediumtext NOT NULL,
  deny_gid mediumtext NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS fcontact (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  url char(255) NOT NULL,
  `name` char(255) NOT NULL,
  photo char(255) NOT NULL,
  request char(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS ffinder (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  uid int(10) unsigned NOT NULL,
  cid int(10) unsigned NOT NULL,
  fid int(10) unsigned NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS fsuggest (
  id int(11) NOT NULL AUTO_INCREMENT,
  uid int(11) NOT NULL,
  cid int(11) NOT NULL,
  `name` char(255) NOT NULL,
  url char(255) NOT NULL,
  request char(255) NOT NULL,
  photo char(255) NOT NULL,
  note text NOT NULL,
  created datetime NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `group` (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  uid int(10) unsigned NOT NULL,
  deleted tinyint(1) NOT NULL DEFAULT '0',
  `name` char(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS group_member (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  uid int(10) unsigned NOT NULL,
  gid int(10) unsigned NOT NULL,
  `contact-id` int(10) unsigned NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS hook (
  id int(11) NOT NULL AUTO_INCREMENT,
  hook char(255) NOT NULL,
  `file` char(255) NOT NULL,
  `function` char(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS intro (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  uid int(10) unsigned NOT NULL,
  fid int(11) NOT NULL DEFAULT '0',
  `contact-id` int(11) NOT NULL,
  knowyou tinyint(1) NOT NULL,
  duplex tinyint(1) NOT NULL DEFAULT '0',
  note text NOT NULL,
  `hash` char(255) NOT NULL,
  `datetime` datetime NOT NULL,
  blocked tinyint(1) NOT NULL DEFAULT '1',
  `ignore` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS item (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  uri char(255) NOT NULL,
  uid int(10) unsigned NOT NULL DEFAULT '0',
  contact_id int(10) unsigned NOT NULL DEFAULT '0',
  `type` char(255) NOT NULL,
  wall tinyint(1) NOT NULL DEFAULT '0',
  gravity tinyint(1) NOT NULL DEFAULT '0',
  parent int(10) unsigned NOT NULL DEFAULT '0',
  parent_uri char(255) NOT NULL,
  extid char(255) NOT NULL,
  thr_parent char(255) NOT NULL,
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  edited datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  received datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `changed` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  owner_name char(255) NOT NULL,
  owner_link char(255) NOT NULL,
  owner_avatar char(255) NOT NULL,
  author_name char(255) NOT NULL,
  author_link char(255) NOT NULL,
  author_avatar char(255) NOT NULL,
  title char(255) NOT NULL,
  body mediumtext NOT NULL,
  app char(255) NOT NULL,
  verb char(255) NOT NULL,
  object_type char(255) NOT NULL,
  object text NOT NULL,
  target_type char(255) NOT NULL,
  target text NOT NULL,
  plink char(255) NOT NULL,
  resource_id char(255) NOT NULL,
  event_id int(10) unsigned NOT NULL,
  tag mediumtext NOT NULL,
  attach mediumtext NOT NULL,
  inform mediumtext NOT NULL,
  location char(255) NOT NULL,
  coord char(255) NOT NULL,
  allow_cid mediumtext NOT NULL,
  allow_gid mediumtext NOT NULL,
  deny_cid mediumtext NOT NULL,
  deny_gid mediumtext NOT NULL,
  private tinyint(1) NOT NULL DEFAULT '0',
  pubmail tinyint(1) NOT NULL DEFAULT '0',
  visible tinyint(1) NOT NULL DEFAULT '0',
  starred tinyint(1) NOT NULL DEFAULT '0',
  unseen tinyint(1) NOT NULL DEFAULT '1',
  deleted tinyint(1) NOT NULL DEFAULT '0',
  last_child tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (id),
  KEY uri (uri),
  KEY uid (uid),
  KEY `contact-id` (contact_id),
  KEY `type` (`type`),
  KEY wall (wall),
  KEY parent (parent),
  KEY `parent-uri` (parent_uri),
  KEY extid (extid),
  KEY created (created),
  KEY edited (edited),
  KEY received (received),
  KEY visible (visible),
  KEY starred (starred),
  KEY deleted (deleted),
  KEY `last-child` (last_child),
  KEY unseen (unseen),
  FULLTEXT KEY title (title),
  FULLTEXT KEY body (body),
  FULLTEXT KEY allow_cid (allow_cid),
  FULLTEXT KEY allow_gid (allow_gid),
  FULLTEXT KEY deny_cid (deny_cid),
  FULLTEXT KEY deny_gid (deny_gid)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS mail (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  uid int(10) unsigned NOT NULL,
  from_name char(255) NOT NULL,
  from_photo char(255) NOT NULL,
  from_url char(255) NOT NULL,
  contact_id char(255) NOT NULL,
  title char(255) NOT NULL,
  body mediumtext NOT NULL,
  seen tinyint(1) NOT NULL,
  replied tinyint(1) NOT NULL,
  uri char(255) NOT NULL,
  parent_uri char(255) NOT NULL,
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS pconfig (
  id int(11) NOT NULL AUTO_INCREMENT,
  uid int(11) NOT NULL DEFAULT '0',
  cat char(255) NOT NULL,
  k char(255) NOT NULL,
  v mediumtext NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS person (
  pid int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` char(255) NOT NULL,
  url char(255) NOT NULL,
  photo char(255) NOT NULL,
  guid char(255) NOT NULL,
  PRIMARY KEY (pid),
  KEY `name` (`name`),
  KEY url (url),
  KEY photo (photo),
  KEY guid (guid)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS photo (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  uid int(10) unsigned NOT NULL,
  contact_id int(10) unsigned NOT NULL,
  resource_id char(255) NOT NULL,
  created datetime NOT NULL,
  edited datetime NOT NULL,
  title char(255) NOT NULL,
  `desc` text NOT NULL,
  album char(255) NOT NULL,
  filename char(255) NOT NULL,
  height smallint(6) NOT NULL,
  width smallint(6) NOT NULL,
  `data` mediumblob NOT NULL,
  scale tinyint(3) NOT NULL,
  `profile` tinyint(1) NOT NULL DEFAULT '0',
  allow_cid mediumtext NOT NULL,
  allow_gid mediumtext NOT NULL,
  deny_cid mediumtext NOT NULL,
  deny_gid mediumtext NOT NULL,
  PRIMARY KEY (id),
  KEY uid (uid),
  KEY `resource-id` (resource_id),
  KEY album (album),
  KEY scale (scale),
  KEY `profile` (`profile`),
  FULLTEXT KEY allow_cid (allow_cid),
  FULLTEXT KEY allow_gid (allow_gid),
  FULLTEXT KEY deny_cid (deny_cid),
  FULLTEXT KEY deny_gid (deny_gid)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `profile` (
  id int(11) NOT NULL AUTO_INCREMENT,
  uid int(11) NOT NULL,
  profile_name char(255) NOT NULL,
  is_default tinyint(1) NOT NULL DEFAULT '0',
  hide_friends tinyint(1) NOT NULL DEFAULT '0',
  `name` char(255) NOT NULL,
  pdesc char(255) NOT NULL,
  dob char(32) NOT NULL DEFAULT '0000-00-00',
  address char(255) NOT NULL,
  locality char(255) NOT NULL,
  region char(255) NOT NULL,
  postal_code char(32) NOT NULL,
  country_name char(255) NOT NULL,
  gender char(32) NOT NULL,
  marital char(255) NOT NULL,
  showwith tinyint(1) NOT NULL DEFAULT '0',
  `with` text NOT NULL,
  sexual char(255) NOT NULL,
  politic char(255) NOT NULL,
  religion char(255) NOT NULL,
  pub_keywords text NOT NULL,
  prv_keywords text NOT NULL,
  about text NOT NULL,
  summary char(255) NOT NULL,
  music text NOT NULL,
  book text NOT NULL,
  tv text NOT NULL,
  film text NOT NULL,
  interest text NOT NULL,
  romance text NOT NULL,
  `work` text NOT NULL,
  education text NOT NULL,
  contact text NOT NULL,
  homepage char(255) NOT NULL,
  photo char(255) NOT NULL,
  thumb char(255) NOT NULL,
  publish tinyint(1) NOT NULL DEFAULT '0',
  net_publish tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  FULLTEXT KEY pub_keywords (pub_keywords),
  FULLTEXT KEY prv_keywords (prv_keywords)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS profile_check (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  uid int(10) unsigned NOT NULL,
  cid int(10) unsigned NOT NULL,
  dfrn_id char(255) NOT NULL,
  sec char(255) NOT NULL,
  expire int(11) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS queue (
  id int(11) NOT NULL AUTO_INCREMENT,
  cid int(11) NOT NULL,
  network char(32) NOT NULL,
  created datetime NOT NULL,
  `last` datetime NOT NULL,
  content mediumtext NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS register (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  `hash` char(255) NOT NULL,
  created datetime NOT NULL,
  uid int(11) unsigned NOT NULL,
  `password` char(255) NOT NULL,
  `language` char(16) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `server` (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  `server` char(255) NOT NULL,
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  accessed datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (id),
  KEY `server` (`server`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `session` (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sid char(255) NOT NULL,
  `data` text NOT NULL,
  expire int(10) unsigned NOT NULL,
  PRIMARY KEY (id),
  KEY sid (sid),
  KEY expire (expire)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS tokens (
  id varchar(40) NOT NULL,
  client_id varchar(20) NOT NULL,
  expires int(11) NOT NULL,
  scope varchar(200) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `user` (
  uid int(11) NOT NULL AUTO_INCREMENT,
  username char(255) NOT NULL,
  `password` char(255) NOT NULL,
  nickname char(255) NOT NULL,
  email char(255) NOT NULL,
  openid char(255) NOT NULL,
  timezone char(128) NOT NULL,
  `language` char(32) NOT NULL DEFAULT 'en',
  register_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  login_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  default_location char(255) NOT NULL,
  allow_location tinyint(1) NOT NULL DEFAULT '0',
  theme char(255) NOT NULL,
  pubkey text NOT NULL,
  prvkey text NOT NULL,
  spubkey text NOT NULL,
  sprvkey text NOT NULL,
  verified tinyint(1) unsigned NOT NULL DEFAULT '0',
  blocked tinyint(1) unsigned NOT NULL DEFAULT '0',
  blockwall tinyint(1) unsigned NOT NULL DEFAULT '0',
  hidewall tinyint(1) unsigned NOT NULL DEFAULT '0',
  notify_flags int(11) unsigned NOT NULL DEFAULT '65535',
  page_flags int(11) unsigned NOT NULL DEFAULT '0',
  prvnets tinyint(1) NOT NULL DEFAULT '0',
  pwdreset char(255) NOT NULL,
  maxreq int(11) NOT NULL DEFAULT '10',
  expire int(11) unsigned NOT NULL DEFAULT '0',
  allow_cid mediumtext NOT NULL,
  allow_gid mediumtext NOT NULL,
  deny_cid mediumtext NOT NULL,
  deny_gid mediumtext NOT NULL,
  openidserver text NOT NULL,
  PRIMARY KEY (uid),
  KEY nickname (nickname)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
