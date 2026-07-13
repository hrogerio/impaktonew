<?php
if (opcache_reset()) {
    echo 'OPcache limpo!';
} else {
    echo 'OPcache não estava ativo ou falhou.';
}