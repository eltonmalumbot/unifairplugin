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
 * Client-side "max choices" guard on the university checkbox list. This is
 * a UX convenience only — the authoritative check runs server-side in
 * locallib.php::unifair_apply_choices().
 *
 * @module      mod_unifair/choicelimit
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {get_string as getString} from 'core/str';

/**
 * @param {Object} params
 * @param {Number} params.maxchoices
 * @return {void}
 */
export const init = (params) => {
    const maxChoices = params.maxchoices;
    const checkboxes = document.querySelectorAll('.uni-checkbox');

    checkboxes.forEach((cb) => {
        cb.addEventListener('change', async() => {
            const checked = document.querySelectorAll('.uni-checkbox:checked');
            if (checked.length > maxChoices) {
                cb.checked = false;
                const message = await getString('error_toomanychoices', 'mod_unifair', maxChoices);
                // eslint-disable-next-line no-alert
                window.alert(message);
            }
        });
    });
};
