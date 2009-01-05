
INSERT INTO jacl2_group (id_aclgrp, name, grouptype, ownerlogin) VALUES (1, 'admins', 0, NULL);
INSERT INTO jacl2_group (id_aclgrp, name, grouptype, ownerlogin) VALUES (2, 'users', 0, NULL);
INSERT INTO jacl2_group (id_aclgrp, name, grouptype, ownerlogin) VALUES (3, 'admin', 2, 'admin');
SELECT setval('jacl2_group_id_aclgrp_seq', 2, true);

INSERT INTO jacl2_user_group (login, id_aclgrp) VALUES ('admin', 1);
INSERT INTO jacl2_user_group (login, id_aclgrp) VALUES ('admin', 3);

INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('acl.user.view', 'jelix~acl2db.acl.user.view');
INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('acl.user.modify', 'jelix~acl2db.acl.user.modify');
INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('acl.group.modify', 'jelix~acl2db.acl.group.modify');
INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('acl.group.create', 'jelix~acl2db.acl.group.create');
INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('acl.group.delete', 'jelix~acl2db.acl.group.delete');
INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('acl.group.view', 'jelix~acl2db.acl.group.view');

INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('acl.group.modify', 1, '');
INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('acl.group.create', 1, '');
INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('acl.group.delete', 1, '');
INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('acl.group.view', 1, '');
INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('acl.user.modify', 1, '');
INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('acl.user.view', 1, '');


INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('auth.user.list', 'jelix~auth.acl.user.list');
INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('auth.user.view', 'jelix~auth.acl.user.view');
INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('auth.user.modify', 'jelix~auth.acl.user.modify');
INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('auth.user.create', 'jelix~auth.acl.user.create');
INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('auth.user.delete', 'jelix~auth.acl.user.delete');
INSERT INTO jacl2_subject (id_aclsbj, label_key) VALUES ('auth.user.change.password', 'jelix~auth.acl.user.change.password');

INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('auth.user.list', 1, '');
INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('auth.user.view', 1, '');
INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('auth.user.modify', 1, '');
INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('auth.user.create', 1, '');
INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('auth.user.delete', 1, '');
INSERT INTO jacl2_rights (id_aclsbj, id_aclgrp, id_aclres) VALUES ('auth.user.change.password', 1, '');





