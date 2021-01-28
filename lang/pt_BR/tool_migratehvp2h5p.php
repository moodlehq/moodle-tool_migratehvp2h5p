<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     tool_migratehvp2h5p
 * @category    string
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['attempted'] = 'Usuários tentados';
$string['cannot_migrate'] = 'Não é possível migrar a atividade';
$string['contenttype'] = 'Tipo de conteúdo';
$string['copy2cb'] = 'Esses conteúdos devem ser adicionados ao banco de conteúdo?';
$string['copy2cb_yeswithlink'] = 'Sim, e um link para esses arquivos deve ser usado na atividade';
$string['copy2cb_yeswithoutlink'] = 'Sim, mas uma cópia será usada na atividade (alterações no banco de conteúdo não serão refletidas na atividade)';
$string['copy2cb_no'] = 'Não, eles devem ser criados apenas na atividade.';
$string['event_hvp_migrated'] = 'mod_hvp migrado para mod_h5pactivity';
$string['graded'] = 'Usuários avaliados';
$string['hvpactivities'] = 'Atividades mod_hvp pendentes';
$string['id'] = 'Id';
$string['migrate'] = 'Migrar';
$string['migrate_success'] = 'Atividade Hvp com id {$a} migrada com sucesso';
$string['migrate_fail'] = 'Erro de migração de atividade hvp com id {$a}';
$string['migrate_gradesoverridden'] = 'Atividade mod_hvp original "{$a->name}", com id {$a->id}, migrado com sucesso. Contudo,
    tem algumas informações de classificação substituídas, como feedback, que não foi migrado porque a atividade original é
    configurado com uma nota máxima inválida (tem que ser maior que 0 para ser migrado para o livro de notas).';
$string['migrate_gradesoverridden_notdelete'] = 'Atividade mod_hvp original "{$a->name}", com id {$a->id}, migrado com sucesso.
    Contudo, tem algumas informações de classificação substituídas, como feedback, que não foi migrado porque a atividade original
    está configurado com uma nota máxima inválida (tem que ser maior que 0 para ser migrado para o livro de notas).
    A atividade original foi ocultada em vez de removida.';
$string['nohvpactivities'] = 'Não há atividades mod_hvp para migrar para mod_h5pactivity.';
$string['pluginname'] = 'Migrar conteúdo de mod_hvp para mod_h5pactivity';
$string['keeporiginal'] = 'Selecione o que fazer com a atividade original depois de migrada';
$string['keeporiginal_hide'] = 'Esconder a atividade original';
$string['keeporiginal_delete'] = 'Exclua a atividade original';
$string['keeporiginal_nothing'] = 'Deixe a atividade original como está';
$string['privacy:metadata'] = 'Migrar conteúdo de mod_hvp para mod_h5pactivity não armazena nenhum dado pessoal';
$string['savedstate'] = 'Saved state';
$string['selecthvpactivity'] = 'Selecione {$a} mod_hvp atividade';
$string['settings'] = 'Configurações de migração';
