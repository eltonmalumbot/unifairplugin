// This file is part of Moodle - http://moodle.org/

/**
 * Drag-and-drop session ordering for the management page.
 *
 * @module     mod_unifair/sessionreorder
 * @copyright  2026 BPK PENABUR Jakarta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialise drag-and-drop ordering.
 *
 * @param {Object} config
 */
export const init = (config) => {
    const table = document.getElementById(config.tableId);
    const status = document.getElementById(config.statusId);
    if (!table || !status) {
        return;
    }

    let draggedRow = null;
    let saving = false;

    const setStatus = (message, isError = false) => {
        status.textContent = message;
        status.classList.toggle('text-danger', isError);
        status.classList.toggle('text-success', !isError && message !== config.saving);
    };

    const saveGroup = async(group) => {
        saving = true;
        const rows = Array.from(table.querySelectorAll('tr[data-session-group="' + group + '"]'));
        const order = rows.map((row) => row.dataset.sessionId);
        setStatus(config.saving);

        const body = new URLSearchParams({
            action: 'reorder',
            sesskey: config.sesskey,
            order: order.join(','),
        });

        try {
            const response = await fetch(config.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body: body.toString(),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error('Unable to save session order');
            }
            rows.forEach((row, index) => {
                const orderCell = row.cells[3];
                if (orderCell) {
                    orderCell.textContent = String(index + 1);
                }
            });
            setStatus(result.message || config.saved);
        } catch (error) {
            setStatus(config.error, true);
            window.setTimeout(() => window.location.reload(), 1500);
        } finally {
            saving = false;
        }
    };

    table.querySelectorAll('[data-unifair-drag-handle]').forEach((handle) => {
        handle.addEventListener('dragstart', (event) => {
            if (saving) {
                event.preventDefault();
                return;
            }
            draggedRow = handle.closest('tr[data-session-id]');
            if (!draggedRow) {
                event.preventDefault();
                return;
            }
            draggedRow.classList.add('unifair-session-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', draggedRow.dataset.sessionId);
        });

        handle.addEventListener('dragend', () => {
            if (draggedRow) {
                draggedRow.classList.remove('unifair-session-dragging');
            }
            draggedRow = null;
        });
    });

    table.addEventListener('dragover', (event) => {
        const target = event.target.closest('tr[data-session-id]');
        if (!draggedRow || !target || target === draggedRow ||
                target.dataset.sessionGroup !== draggedRow.dataset.sessionGroup) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        const targetBox = target.getBoundingClientRect();
        if (event.clientY < targetBox.top + targetBox.height / 2) {
            target.before(draggedRow);
        } else {
            target.after(draggedRow);
        }
    });

    table.addEventListener('drop', (event) => {
        if (!draggedRow) {
            return;
        }
        event.preventDefault();
        const group = draggedRow.dataset.sessionGroup;
        draggedRow.classList.remove('unifair-session-dragging');
        draggedRow = null;
        saveGroup(group);
    });
};
