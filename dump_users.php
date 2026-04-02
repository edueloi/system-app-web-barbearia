<?php
$p=new PDO('sqlite:banco_salao.sqlite');
print_r($p->query('SELECT id, estabelecimento FROM usuarios')->fetchAll(PDO::FETCH_ASSOC));
