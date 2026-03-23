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

namespace paygw_transfer\admin;

use admin_setting_confightmleditor;
use context_system;
use core\output\html_writer;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/adminlib.php");
require_once("{$CFG->libdir}/filelib.php");

/**
 * Override to add some images to the text.
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_confightmleditorwithimages extends admin_setting_confightmleditor {
    #[\Override()]
    public function output_html($data, $query = '') {
        $context = \context_system::instance();

        $draftitemid = file_get_submitted_draft_itemid($this->get_id());

        $options = [
            'subdirs'  => false,
            'maxfiles' => 10,
            'maxbytes' => 0,
            'context'  => $context,
        ];

        // Prepare draft area.
        $data = file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'paygw_transfer',
            $this->name,
            0,
            $options,
            $data
        );

        $editor = editors_get_preferred_editor(FORMAT_HTML);

        $editoroptions = [
            'context' => $context,
            'maxfiles' => 10,
            'maxbytes' => 0,
            'subdirs' => false,
            'accepted_types' => ['web_image'],
            'height' => 300,
        ];

        $args = new stdClass();
        // Need these three to filter repositories list.
        $args->accepted_types = ['web_image'];
        $args->return_types = FILE_INTERNAL | FILE_EXTERNAL;
        $args->context = $context;
        $args->env = 'filepicker';
        // ...advimage plugin.
        $imageoptions = initialise_filepicker($args);
        $imageoptions->context = $context;
        $imageoptions->client_id = uniqid();
        $imageoptions->maxbytes = $options['maxbytes'];
        $imageoptions->env = 'editor';
        $imageoptions->itemid = $draftitemid;
        $fpoptions['image'] = $imageoptions;
        // Enable editor + filepicker.
        $editor->use_editor($this->get_id(), $editoroptions, $fpoptions);

        $textarea = html_writer::tag('textarea',
            s($data),
            [
                'name' => $this->get_full_name(),
                'id'   => $this->get_id(),
                'rows' => 30,
                'cols' => 80,
            ]
        );

        return format_admin_setting(
            $this,
            $this->visiblename,
            $textarea,
            $this->description,
            true,
            '',
            null,
            $query
        );
    }

    #[\Override()]
    public function write_setting($data) {

        $context = \context_system::instance();

        $draftitemid = file_get_submitted_draft_itemid($this->get_id());

        $options = [
            'subdirs' => false,
            'maxfiles' => 10,
            'maxbytes' => 0,
        ];

        $data = file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'paygw_transfer',
            $this->name,
            0,
            $options,
            $data
        );

        return parent::write_setting($data);
    }
    /**
     * Return the configuration formatted with the images correctly shown.
     * @param string $config the config name (without paygw_transfer)
     * @return string
     */
    public static function get_formatted_text(string $config) {
        $text = get_config('paygw_transfer', $config);

        if (empty($text) || empty(trim(html_to_text($text)))) {
            return '';
        }

        $context = context_system::instance();

        $text = file_rewrite_pluginfile_urls(
            $text,
            'pluginfile.php',
            $context->id,
            'paygw_transfer',
            $config,
            0
        );

        return format_text($text, FORMAT_HTML, ['context' => $context]);
    }
}
