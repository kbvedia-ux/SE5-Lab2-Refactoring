function openPortalModal(selector) {
    var modal = document.querySelector(selector);
    if (!modal) {
        return false;
    }

    modal.removeAttribute('hidden');
    modal.classList.add('is-open');
    document.body.classList.add('modal-open');
    return true;
}

window.openPortalModal = openPortalModal;

function getInitials(name) {
    return (name || 'T')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map(function (part) { return part.charAt(0).toUpperCase(); })
        .join('') || 'T';
}

function fillTenantEditModal(button) {
    var modal = document.querySelector('#edit-tenant-modal');
    if (!modal || !button) {
        return;
    }

    var name = button.getAttribute('data-name') || '';
    var house = button.getAttribute('data-house') || '';
    var room = button.getAttribute('data-room') || '';
    var houseId = button.getAttribute('data-house-id') || '';
    var roomId = button.getAttribute('data-room-id') || '';

    modal.querySelector('#edit_tenant_id').value = button.getAttribute('data-tenant-id') || '';
    modal.querySelector('#edit_full_name').value = name;
    modal.querySelector('#edit_course').value = button.getAttribute('data-course') || '';
    modal.querySelector('#edit_email').value = button.getAttribute('data-email') || '';
    modal.querySelector('#edit_phone').value = button.getAttribute('data-phone') || '';
    modal.querySelector('#edit_move_in_date').value = button.getAttribute('data-move-in') || '';
    modal.querySelector('#edit_monthly_rent').value = button.getAttribute('data-rent') || '';
    modal.querySelector('#edit_payment_method').value = button.getAttribute('data-payment-method') || 'GCash';
    modal.querySelector('#edit_status').value = button.getAttribute('data-status') || '';
    modal.querySelector('#edit_tenant_initials').textContent = getInitials(name);
    modal.querySelector('#edit_tenant_summary_name').textContent = name || 'Tenant Name';
    modal.querySelector('#edit_tenant_summary_room').textContent = room ? 'Room ' + room : 'Room';
    modal.querySelector('#edit_tenant_summary_house').textContent = house || 'Boarding House';

    var houseSelect = modal.querySelector('#edit_house');
    var roomSelect = modal.querySelector('#edit_room');
    if (houseSelect) {
        houseSelect.value = houseId;
        houseSelect.dispatchEvent(new Event('change'));
    }
    if (roomSelect) {
        roomSelect.value = roomId;
    }
}

document.addEventListener('click', function (event) {
    var tenantEditTrigger = event.target.closest('.tenant-edit-trigger');
    if (tenantEditTrigger) {
        fillTenantEditModal(tenantEditTrigger);
    }

    var hashLink = event.target.closest('a[href^="#"]');
    if (hashLink && hashLink.hash) {
        if (openPortalModal(hashLink.hash)) {
            event.preventDefault();
        }
    }

    var searchIcon = event.target.closest('.portal-search i, .admin-search i');
    if (searchIcon) {
        var searchBox = searchIcon.closest('.portal-search, .admin-search');
        var searchInput = searchBox ? searchBox.querySelector('[data-search-submit]') : null;
        if (searchInput) {
            filterVisibleSearchRows(searchInput);
            submitFilterForm(searchInput.form);
        }
    }

    var opener = event.target.closest('[data-modal-target]');
    if (opener) {
        var targetSelector = opener.getAttribute('data-modal-target');
        if (targetSelector && openPortalModal(targetSelector)) {
            event.preventDefault();
        }
    }

    if (event.target.matches('[data-modal-close]') || event.target.classList.contains('modal-overlay')) {
        var activeModal = event.target.closest('.modal-overlay') || document.querySelector('.modal-overlay.is-open');
        if (activeModal) {
            activeModal.classList.remove('is-open');
            activeModal.setAttribute('hidden', '');
        }
        if (!document.querySelector('.modal-overlay.is-open')) {
            document.body.classList.remove('modal-open');
        }
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
        return;
    }

    document.querySelectorAll('.modal-overlay.is-open').forEach(function (modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('hidden', '');
    });
    document.body.classList.remove('modal-open');
});

document.querySelectorAll('.dependent-room-form').forEach(function (form) {
    var houseSelect = form.querySelector('.tenant-house-select');
    var roomSelect = form.querySelector('.tenant-room-select');
    if (!houseSelect || !roomSelect) {
        return;
    }

    var allOptions = Array.from(roomSelect.querySelectorAll('option[data-house-id]')).map(function (option) {
        return {
            value: option.value,
            text: option.textContent,
            houseId: option.getAttribute('data-house-id'),
            selected: option.selected
        };
    });

    function updateRooms() {
        var selectedHouse = houseSelect.value;
        var selectedRoom = roomSelect.value;
        roomSelect.innerHTML = '<option value="">Select vacant room</option>';

        allOptions.forEach(function (item) {
            if (item.houseId !== selectedHouse) {
                return;
            }
            var option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.text;
            option.selected = item.value === selectedRoom || (!selectedRoom && item.selected);
            roomSelect.appendChild(option);
        });
    }

    houseSelect.addEventListener('change', updateRooms);
    updateRooms();
});

function submitFilterForm(form) {
    if (!form) {
        return;
    }

    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
        return;
    }

    form.submit();
}

function normalizeSearchText(value) {
    return (value || '').toString().toLowerCase().trim();
}

function filterVisibleSearchRows(input) {
    var rows = document.querySelectorAll('[data-search-row]');
    if (!rows.length) {
        return;
    }

    var query = normalizeSearchText(input.value);
    rows.forEach(function (row) {
        var haystack = normalizeSearchText(row.getAttribute('data-search-text') || row.textContent);
        row.hidden = query !== '' && haystack.indexOf(query) === -1;
    });
}

document.querySelectorAll('[data-auto-submit]').forEach(function (control) {
    control.addEventListener('change', function () {
        submitFilterForm(control.form);
    });
});

document.querySelectorAll('[data-search-submit]').forEach(function (input) {
    filterVisibleSearchRows(input);

    input.addEventListener('input', function () {
        filterVisibleSearchRows(input);
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && input.form) {
            event.preventDefault();
            submitFilterForm(input.form);
        }
    });

    input.addEventListener('search', function () {
        filterVisibleSearchRows(input);
        if (input.value === '') {
            submitFilterForm(input.form);
        }
    });
});

document.querySelectorAll('[data-document-type]').forEach(function (select) {
    var form = select.closest('form');
    var limitField = form ? form.querySelector('[data-room-limit-field]') : null;
    var limitInput = limitField ? limitField.querySelector('input') : null;
    if (!limitField || !limitInput) {
        return;
    }

    function updateRoomLimitField() {
        var isAccreditation = select.value === 'Accreditation';
        limitField.hidden = !isAccreditation;
        limitInput.disabled = !isAccreditation;
        limitInput.required = isAccreditation;
        if (!isAccreditation) {
            limitInput.value = '';
        }
    }

    select.addEventListener('change', updateRoomLimitField);
    updateRoomLimitField();
});

document.querySelectorAll('[data-file-upload]').forEach(function (uploadBox) {
    var input = uploadBox.querySelector('[data-file-input]');
    var emptyState = uploadBox.querySelector('[data-file-empty]');
    var selectedState = uploadBox.querySelector('[data-file-selected]');
    var fileName = uploadBox.querySelector('[data-file-name]');
    var fileSize = uploadBox.querySelector('[data-file-size]');
    var removeButton = uploadBox.querySelector('[data-file-remove]');
    if (!input || !emptyState || !selectedState || !fileName || !fileSize) {
        return;
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        }
        if (bytes < 1024 * 1024) {
            return (bytes / 1024).toFixed(1) + ' KB';
        }
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function updateFileState() {
        var file = input.files && input.files[0];
        emptyState.hidden = Boolean(file);
        selectedState.hidden = !file;
        uploadBox.classList.toggle('has-file', Boolean(file));
        if (file) {
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
        }
    }

    input.addEventListener('change', updateFileState);
    if (removeButton) {
        removeButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            input.value = '';
            updateFileState();
        });
    }
    updateFileState();
});

document.addEventListener('submit', function (event) {
    if (!event.target.matches('[data-client-only]')) {
        return;
    }

    event.preventDefault();
    var modal = event.target.closest('.modal-overlay');
    if (modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('hidden', '');
    }
    if (!document.querySelector('.modal-overlay.is-open')) {
        document.body.classList.remove('modal-open');
    }
});
