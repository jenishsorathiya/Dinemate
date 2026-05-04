<?php
/**
 * Reusable admin booking editing modal.
 *
 * Optional variables before include:
 *   $bookingEditModalId = 'bookingEditModal';
 *   $bookingEditFormId = 'bookingEditForm';
 *   $bookingEditTitle = 'Edit Booking';
 *   $bookingEditSubmitLabel = 'Save Changes';
 *   $bookingEditShowDelete = true;
 *   $bookingEditShowStatus = true;
 *   $bookingEditShowTable = true;
 *   $bookingEditHiddenFields = [
 *       ['name' => 'customer_profile_id', 'value' => '', 'attributes' => 'data-booking-edit-customer-profile-id'],
 *   ];
 *   $bookingEditTables = [
 *       ['table_id' => 1, 'table_number' => '1', 'area_name' => 'Main', 'capacity' => 4],
 *   ];
 *   Table assignment renders as a multi-select checkbox picker and submits table_ids[].
 */
$bookingEditModalId = $bookingEditModalId ?? 'bookingEditModal';
$bookingEditFormId = $bookingEditFormId ?? 'bookingEditForm';
$bookingEditTitle = $bookingEditTitle ?? 'Edit Booking';
$bookingEditSubmitLabel = $bookingEditSubmitLabel ?? 'Save Changes';
$bookingEditShowDelete = $bookingEditShowDelete ?? true;
$bookingEditShowStatus = $bookingEditShowStatus ?? true;
$bookingEditShowTable = $bookingEditShowTable ?? true;
$bookingEditShowFloor = $bookingEditShowFloor ?? true;
$bookingEditHiddenFields = $bookingEditHiddenFields ?? [];
$bookingEditTables = $bookingEditTables ?? [];
$bookingEditFloorLayoutHtml = $bookingEditFloorLayoutHtml ?? '';
$bookingEditTypes = $bookingEditTypes ?? (function_exists('getBookingTypes') ? getBookingTypes() : ['normal', 'trivia', 'function']);
$bookingEditStatuses = $bookingEditStatuses ?? [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'completed' => 'Completed',
    'no_show' => 'No-show',
    'cancelled' => 'Cancelled',
];

$bookingEditModalIdAttr = htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8');
$bookingEditFormIdAttr = htmlspecialchars($bookingEditFormId, ENT_QUOTES, 'UTF-8');
$bookingEditTitleAttr = htmlspecialchars($bookingEditTitle, ENT_QUOTES, 'UTF-8');
$bookingEditSubmitLabelAttr = htmlspecialchars($bookingEditSubmitLabel, ENT_QUOTES, 'UTF-8');
$bookingEditPanelPrefixAttr = htmlspecialchars($bookingEditModalId . 'Panel', ENT_QUOTES, 'UTF-8');
$bookingEditFooterClass = $bookingEditShowDelete ? 'booking-edit-footer' : 'booking-edit-footer no-secondary';
?>
<style>
    .booking-edit-modal[hidden] { display: none; }

    .booking-edit-modal {
        position: fixed;
        inset: 0;
        z-index: 120;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(17, 24, 39, 0.42);
    }

    .booking-edit-dialog {
        width: min(760px, 100%);
        max-height: calc(100vh - 40px);
        overflow: hidden;
        background: var(--dm-surface);
        border: 1px solid var(--dm-border);
        border-radius: 8px;
        box-shadow: var(--dm-shadow-md);
    }

    .booking-edit-form {
        display: grid;
        grid-template-rows: auto auto minmax(280px, 1fr) auto;
        max-height: calc(100vh - 40px);
    }

    .booking-edit-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        padding: 20px 22px 14px;
    }

    .booking-edit-title {
        margin: 0;
        color: var(--dm-text);
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 0;
        line-height: 1.15;
    }

    .booking-edit-close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        flex: 0 0 auto;
        border: 1px solid var(--dm-border);
        border-radius: 8px;
        background: var(--dm-surface);
        color: var(--dm-text);
        cursor: pointer;
        font-size: 18px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    }

    .booking-edit-tabs {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        padding: 0 22px;
        border-bottom: 1px solid var(--dm-border);
    }

    .booking-edit-tab {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        min-width: 0;
        min-height: 52px;
        border: 0;
        background: transparent;
        color: var(--dm-text-muted);
        cursor: pointer;
        font: inherit;
        font-size: 14px;
        font-weight: 800;
        letter-spacing: 0;
        white-space: nowrap;
    }

    .booking-edit-tab i {
        width: 22px;
        text-align: center;
        font-size: 17px;
    }

    .booking-edit-tab span {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .booking-edit-tab.is-active {
        color: var(--dm-primary);
    }

    .booking-edit-tab.is-active::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        height: 3px;
        border-radius: 999px;
        background: var(--dm-primary);
    }

    .booking-edit-body {
        min-height: 300px;
        overflow-x: auto;
        overflow-y: auto;
        padding: 22px;
    }

    .booking-edit-panel {
        display: grid;
        gap: 18px;
        min-width: 0;
    }

    .booking-edit-error {
        display: none;
        margin-bottom: 14px;
        padding: 10px 12px;
        border: 1px solid var(--dm-danger-border);
        border-radius: 8px;
        background: var(--dm-danger-bg);
        color: var(--dm-danger-text);
        font-size: 13px;
        font-weight: 700;
    }

    .booking-edit-error.is-visible { display: block; }

    .booking-edit-panel {
        display: grid;
        gap: 18px;
    }

    .booking-edit-panel[hidden] { display: none; }

    .booking-edit-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px 20px;
    }

    .booking-edit-field {
        display: grid;
        gap: 7px;
        min-width: 0;
    }

    .booking-edit-field.full { grid-column: 1 / -1; }

    .booking-edit-floor-panel {
        display: grid;
        gap: 12px;
        overflow: auto;
    }

    .booking-edit-floor-panel .home-floor-viewport {
        max-width: 100%;
        overflow: auto;
    }

    .booking-edit-floor-panel .home-floor-canvas {
        width: 860px;
        height: 600px;
        max-width: none;
        transform-origin: top left;
        transition: transform 0.15s ease;
    }

    .booking-edit-floor-empty {
        min-height: 220px;
        padding: 18px;
        border: 1px solid var(--dm-border);
        border-radius: 8px;
        background: var(--dm-surface);
        color: var(--dm-text-muted);
        font-size: 13px;
        text-align: center;
    }

    .booking-edit-field label {
        color: var(--dm-text-muted);
        font-size: 12px;
        font-weight: 800;
    }

    .booking-edit-control {
        position: relative;
        min-width: 0;
    }

    .booking-edit-control > i {
        position: absolute;
        left: 13px;
        top: 50%;
        z-index: 1;
        transform: translateY(-50%);
        color: var(--dm-text-muted);
        font-size: 14px;
        pointer-events: none;
    }

    .booking-edit-input,
    .booking-edit-select,
    .booking-edit-textarea {
        width: 100%;
        min-width: 0;
        border: 1px solid var(--dm-border);
        border-radius: 8px;
        background: var(--dm-surface);
        color: var(--dm-text);
        font: inherit;
        font-size: 14px;
        line-height: 1.4;
    }

    .booking-edit-input,
    .booking-edit-select {
        min-height: 46px;
        padding: 0 13px;
    }

    .booking-edit-input.with-icon {
        padding-left: 40px;
    }

    .booking-edit-select {
        appearance: none;
        background-image: linear-gradient(45deg, transparent 50%, var(--dm-text-muted) 50%), linear-gradient(135deg, var(--dm-text-muted) 50%, transparent 50%);
        background-position: calc(100% - 18px) 20px, calc(100% - 13px) 20px;
        background-size: 5px 5px, 5px 5px;
        background-repeat: no-repeat;
        padding-right: 36px;
    }

    .booking-table-picker {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
        gap: 8px;
        max-height: 190px;
        overflow: auto;
        padding: 8px;
        border: 1px solid var(--dm-border);
        border-radius: 8px;
        background: #fbfbfc;
    }

    .booking-table-clear,
    .booking-table-option {
        min-width: 0;
        min-height: 42px;
        border: 1px solid var(--dm-border);
        border-radius: 8px;
        background: var(--dm-surface);
        color: var(--dm-text);
    }

    .booking-table-clear {
        display: inline-flex;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        padding: 8px 10px;
        cursor: pointer;
        font: inherit;
        font-size: 12px;
        font-weight: 800;
        text-align: left;
    }

    .booking-table-option {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 2px 8px;
        align-items: center;
        padding: 8px 10px;
        cursor: pointer;
    }

    .booking-table-option input {
        grid-row: span 2;
        width: 16px;
        height: 16px;
        accent-color: var(--dm-primary);
    }

    .booking-table-option:has(input:checked) {
        border-color: var(--dm-primary);
        background: var(--dm-primary-soft);
    }

    .booking-table-option-main {
        overflow: hidden;
        color: var(--dm-text);
        font-size: 12px;
        font-weight: 900;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .booking-table-option-meta,
    .booking-table-empty {
        color: var(--dm-text-muted);
        font-size: 11px;
        font-weight: 700;
    }

    .booking-table-option-meta {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .booking-table-empty {
        grid-column: 1 / -1;
        padding: 10px;
        text-align: center;
    }

    .booking-edit-textarea {
        min-height: 180px;
        padding: 13px;
        resize: vertical;
    }

    .booking-edit-guest-control {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) 38px 38px;
        align-items: center;
        min-height: 46px;
        overflow: hidden;
        border: 1px solid var(--dm-border);
        border-radius: 8px;
        background: var(--dm-surface);
    }

    .booking-edit-guest-control i {
        color: var(--dm-text-muted);
        justify-self: center;
        font-size: 14px;
    }

    .booking-edit-guest-control input {
        width: 100%;
        min-width: 0;
        height: 46px;
        border: 0;
        background: transparent;
        color: var(--dm-text);
        font: inherit;
        font-size: 14px;
        outline: none;
    }

    .booking-edit-guest-control input::-webkit-outer-spin-button,
    .booking-edit-guest-control input::-webkit-inner-spin-button {
        margin: 0;
        appearance: none;
    }

    .booking-edit-step {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 46px;
        border: 0;
        background: transparent;
        color: var(--dm-text-muted);
        cursor: pointer;
        font-size: 20px;
        font-weight: 800;
        line-height: 1;
    }

    .booking-edit-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 22px;
        border-top: 1px solid var(--dm-border);
    }

    .booking-edit-footer.no-secondary {
        justify-content: flex-end;
    }

    .booking-edit-actions,
    .booking-edit-primary-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .booking-edit-danger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 0 16px;
        border: 1px solid var(--dm-danger-border);
        border-radius: 8px;
        background: var(--dm-surface);
        color: var(--dm-danger-text);
        cursor: pointer;
        font: inherit;
        font-size: 13px;
        font-weight: 800;
        gap: 10px;
    }

    .booking-edit-footer .ui-button {
        min-height: 44px;
        min-width: 100px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 800;
    }

    .booking-edit-footer .ui-button-primary {
        min-width: 140px;
    }

    @media (max-width: 760px) {
        .booking-edit-modal {
            align-items: stretch;
            padding: 12px;
        }

        .booking-edit-form {
            grid-template-rows: auto auto minmax(260px, 1fr) auto;
            max-height: calc(100vh - 24px);
        }

        .booking-edit-header,
        .booking-edit-body,
        .booking-edit-footer {
            padding-left: 18px;
            padding-right: 18px;
        }

        .booking-edit-title {
            font-size: 23px;
        }

        .booking-edit-close {
            width: 42px;
            height: 42px;
            font-size: 18px;
        }

        .booking-edit-tabs {
            padding: 0 18px;
        }

        .booking-edit-tab {
            flex-direction: column;
            min-height: 58px;
            gap: 5px;
            font-size: 12px;
            line-height: 1.15;
            white-space: normal;
        }

        .booking-edit-tab i {
            font-size: 16px;
        }

        .booking-edit-body {
            min-height: 0;
        }

        .booking-edit-grid {
            grid-template-columns: 1fr;
        }

        .booking-edit-textarea {
            min-height: 180px;
        }

        .booking-edit-footer {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .booking-edit-actions,
        .booking-edit-primary-actions,
        .booking-edit-danger,
        .booking-edit-footer .ui-button,
        .booking-edit-footer .ui-button-primary {
            width: 100%;
        }
    }
</style>

<div class="booking-edit-modal" id="<?php echo $bookingEditModalIdAttr; ?>" hidden>
    <div class="booking-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo $bookingEditModalIdAttr; ?>Title">
        <form class="booking-edit-form" id="<?php echo $bookingEditFormIdAttr; ?>" data-booking-edit-form>
            <div class="booking-edit-header">
                <h2 class="booking-edit-title" id="<?php echo $bookingEditModalIdAttr; ?>Title"><?php echo $bookingEditTitleAttr; ?></h2>
                <button type="button" class="booking-edit-close" data-booking-edit-close aria-label="Close editor"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="booking-edit-tabs" role="tablist" aria-label="Booking editor sections">
                <button type="button" class="booking-edit-tab is-active" id="<?php echo $bookingEditPanelPrefixAttr; ?>PersonalTab" role="tab" aria-selected="true" aria-controls="<?php echo $bookingEditPanelPrefixAttr; ?>Personal" data-booking-edit-tab="personal">
                    <i class="fa-solid fa-user"></i>
                    <span>Personal Details</span>
                </button>
                <button type="button" class="booking-edit-tab" id="<?php echo $bookingEditPanelPrefixAttr; ?>BookingTab" role="tab" aria-selected="false" aria-controls="<?php echo $bookingEditPanelPrefixAttr; ?>Booking" data-booking-edit-tab="booking">
                    <i class="fa-regular fa-calendar-check"></i>
                    <span>Booking Details</span>
                </button>
                <button type="button" class="booking-edit-tab" id="<?php echo $bookingEditPanelPrefixAttr; ?>TablesTab" role="tab" aria-selected="false" aria-controls="<?php echo $bookingEditPanelPrefixAttr; ?>Tables" data-booking-edit-tab="tables">
                    <i class="fa-solid fa-table-cells-large"></i>
                    <span>Table Details</span>
                </button>
                <button type="button" class="booking-edit-tab" id="<?php echo $bookingEditPanelPrefixAttr; ?>NotesTab" role="tab" aria-selected="false" aria-controls="<?php echo $bookingEditPanelPrefixAttr; ?>Notes" data-booking-edit-tab="notes">
                    <i class="fa-regular fa-note-sticky"></i>
                    <span>Notes</span>
                </button>
            </div>

            <div class="booking-edit-body">
                <div class="booking-edit-error" data-booking-edit-error></div>
                <input type="hidden" name="booking_id" data-booking-edit-id>
                <input type="hidden" name="end_time" data-booking-edit-end>
                <?php foreach ($bookingEditHiddenFields as $hiddenField): ?>
                    <?php
                        $hiddenName = htmlspecialchars((string) ($hiddenField['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $hiddenValue = htmlspecialchars((string) ($hiddenField['value'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $hiddenAttributes = trim((string) ($hiddenField['attributes'] ?? ''));
                    ?>
                    <?php if ($hiddenName !== ''): ?>
                        <input type="hidden" name="<?php echo $hiddenName; ?>" value="<?php echo $hiddenValue; ?>" <?php echo $hiddenAttributes; ?>>
                    <?php endif; ?>
                <?php endforeach; ?>

                <section class="booking-edit-panel" id="<?php echo $bookingEditPanelPrefixAttr; ?>Personal" role="tabpanel" aria-labelledby="<?php echo $bookingEditPanelPrefixAttr; ?>PersonalTab" data-booking-edit-panel="personal">
                    <div class="booking-edit-grid">
                        <div class="booking-edit-field">
                            <label for="<?php echo $bookingEditModalIdAttr; ?>Name">Name</label>
                            <div class="booking-edit-control">
                                <i class="fa-solid fa-user"></i>
                                <input class="booking-edit-input with-icon" type="text" id="<?php echo $bookingEditModalIdAttr; ?>Name" name="customer_name" data-booking-edit-name required>
                            </div>
                        </div>

                        <div class="booking-edit-field">
                            <label for="<?php echo $bookingEditModalIdAttr; ?>Guests">Guests</label>
                            <div class="booking-edit-guest-control">
                                <i class="fa-solid fa-user-group"></i>
                                <input type="number" id="<?php echo $bookingEditModalIdAttr; ?>Guests" name="number_of_guests" min="1" data-booking-edit-guests required>
                                <button type="button" class="booking-edit-step" data-booking-edit-guest-step="-1" aria-label="Decrease guests">-</button>
                                <button type="button" class="booking-edit-step" data-booking-edit-guest-step="1" aria-label="Increase guests">+</button>
                            </div>
                        </div>

                        <div class="booking-edit-field">
                            <label for="<?php echo $bookingEditModalIdAttr; ?>Email">Email</label>
                            <div class="booking-edit-control">
                                <i class="fa-regular fa-envelope"></i>
                                <input class="booking-edit-input with-icon" type="email" id="<?php echo $bookingEditModalIdAttr; ?>Email" name="customer_email" data-booking-edit-email placeholder="Email address">
                            </div>
                        </div>

                        <div class="booking-edit-field">
                            <label for="<?php echo $bookingEditModalIdAttr; ?>Phone">Phone</label>
                            <div class="booking-edit-control">
                                <i class="fa-solid fa-phone"></i>
                                <input class="booking-edit-input with-icon" type="tel" id="<?php echo $bookingEditModalIdAttr; ?>Phone" name="customer_phone" maxlength="30" data-booking-edit-phone placeholder="Phone number">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="booking-edit-panel" id="<?php echo $bookingEditPanelPrefixAttr; ?>Booking" role="tabpanel" aria-labelledby="<?php echo $bookingEditPanelPrefixAttr; ?>BookingTab" data-booking-edit-panel="booking" hidden>
                    <div class="booking-edit-grid">
                        <div class="booking-edit-field">
                            <label for="<?php echo $bookingEditModalIdAttr; ?>Date">Date</label>
                            <div class="booking-edit-control">
                                <i class="fa-regular fa-calendar-days"></i>
                                <input class="booking-edit-input with-icon" type="date" id="<?php echo $bookingEditModalIdAttr; ?>Date" name="booking_date" data-booking-edit-date required>
                            </div>
                        </div>

                        <div class="booking-edit-field">
                            <label for="<?php echo $bookingEditModalIdAttr; ?>Start">Time</label>
                            <div class="booking-edit-control">
                                <i class="fa-regular fa-clock"></i>
                                <input class="booking-edit-input with-icon" type="time" id="<?php echo $bookingEditModalIdAttr; ?>Start" name="start_time" min="10:00" max="21:30" step="1800" data-booking-edit-start required>
                            </div>
                        </div>

                        <div class="booking-edit-field">
                            <label for="<?php echo $bookingEditModalIdAttr; ?>Type">Booking Type</label>
                            <select class="booking-edit-select" id="<?php echo $bookingEditModalIdAttr; ?>Type" name="booking_type" data-booking-edit-type>
                                <?php foreach ($bookingEditTypes as $bookingType): ?>
                                    <?php $bookingTypeLabel = function_exists('getBookingTypeLabel') ? getBookingTypeLabel($bookingType) : ucfirst((string) $bookingType); ?>
                                    <option value="<?php echo htmlspecialchars((string) $bookingType, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($bookingTypeLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($bookingEditShowStatus): ?>
                            <div class="booking-edit-field">
                                <label for="<?php echo $bookingEditModalIdAttr; ?>Status">Status</label>
                                <select class="booking-edit-select" id="<?php echo $bookingEditModalIdAttr; ?>Status" name="status" data-booking-edit-status>
                                    <?php foreach ($bookingEditStatuses as $statusValue => $statusLabel): ?>
                                        <option value="<?php echo htmlspecialchars((string) $statusValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $statusLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <?php if ($bookingEditShowTable): ?>
                            <div class="booking-edit-field full">
                                <label>Tables</label>
                                <input type="hidden" id="<?php echo $bookingEditModalIdAttr; ?>Table" name="table_id" data-booking-edit-table>
                                <div class="booking-table-picker" data-booking-edit-table-picker>
                                    <button type="button" class="booking-table-clear" data-booking-edit-table-clear>
                                        <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                        <span>No table</span>
                                    </button>
                                    <?php if (empty($bookingEditTables)): ?>
                                        <div class="booking-table-empty">No tables available</div>
                                    <?php else: ?>
                                        <?php foreach ($bookingEditTables as $table): ?>
                                            <?php
                                                $tableId = (int) ($table['table_id'] ?? 0);
                                                $tableLabel = 'Table ' . (string) ($table['table_number'] ?? '');
                                                $tableArea = (string) ($table['area_name'] ?? 'Dining room');
                                                $tableCapacity = (int) ($table['capacity'] ?? 0);
                                            ?>
                                            <label class="booking-table-option">
                                                <input type="checkbox" name="table_ids[]" value="<?php echo $tableId; ?>" data-booking-edit-table-option>
                                                <span class="booking-table-option-main"><?php echo htmlspecialchars($tableLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="booking-table-option-meta"><?php echo htmlspecialchars($tableArea, ENT_QUOTES, 'UTF-8'); ?> · <?php echo number_format($tableCapacity); ?> seats</span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="booking-edit-panel" id="<?php echo $bookingEditPanelPrefixAttr; ?>Tables" role="tabpanel" aria-labelledby="<?php echo $bookingEditPanelPrefixAttr; ?>TablesTab" data-booking-edit-panel="tables" hidden>
                    <div class="booking-edit-field full booking-edit-floor-panel">
                        <label>Floor Layout</label>
                        <?php if ($bookingEditFloorLayoutHtml !== ''): ?>
                            <?php echo $bookingEditFloorLayoutHtml; ?>
                        <?php else: ?>
                            <div class="booking-edit-floor-empty">Floor layout is unavailable in this view.</div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="booking-edit-panel" id="<?php echo $bookingEditPanelPrefixAttr; ?>Notes" role="tabpanel" aria-labelledby="<?php echo $bookingEditPanelPrefixAttr; ?>NotesTab" data-booking-edit-panel="notes" hidden>
                    <div class="booking-edit-field full">
                        <label for="<?php echo $bookingEditModalIdAttr; ?>Notes">Notes</label>
                        <textarea class="booking-edit-textarea" id="<?php echo $bookingEditModalIdAttr; ?>Notes" name="special_request" data-booking-edit-notes placeholder="Optional notes"></textarea>
                    </div>
                </section>
            </div>

            <div class="<?php echo htmlspecialchars($bookingEditFooterClass, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($bookingEditShowDelete): ?>
                    <div class="booking-edit-actions">
                        <button type="button" class="booking-edit-danger" data-booking-edit-delete><i class="fa-regular fa-trash-can"></i>Delete Booking</button>
                    </div>
                <?php endif; ?>
                <div class="booking-edit-primary-actions">
                    <button type="button" class="ui-button ui-button-ghost" data-booking-edit-cancel>Cancel</button>
                    <button type="submit" class="ui-button ui-button-primary" data-booking-edit-save><?php echo $bookingEditSubmitLabelAttr; ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById(<?php echo json_encode($bookingEditModalId); ?>);

        if (!modal) {
            return;
        }

        const tabs = Array.from(modal.querySelectorAll('[data-booking-edit-tab]'));
        const panels = Array.from(modal.querySelectorAll('[data-booking-edit-panel]'));
        const guestInput = modal.querySelector('[data-booking-edit-guests]');

        const floorCanvas = modal.querySelector('.booking-edit-floor-panel .home-floor-canvas');
        const floorViewport = modal.querySelector('.booking-edit-floor-panel .home-floor-viewport');

        const updateFloorLayoutScale = () => {
            if (!floorCanvas || !floorViewport) {
                return;
            }

            const availableWidth = Math.max(0, floorViewport.clientWidth);
            const layoutWidth = floorCanvas.scrollWidth || floorCanvas.clientWidth;
            const scale = layoutWidth > 0 ? Math.min(1, availableWidth / layoutWidth) : 1;

            floorCanvas.style.transform = scale < 1 ? `scale(${scale})` : '';
            floorCanvas.style.width = '860px';
        };

        const setActiveTab = (target) => {
            tabs.forEach((tab) => {
                const isActive = tab.dataset.bookingEditTab === target;
                tab.classList.toggle('is-active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                tab.tabIndex = isActive ? 0 : -1;
            });

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.bookingEditPanel !== target;
            });

            if (target === 'tables') {
                updateFloorLayoutScale();
            }
        };

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => setActiveTab(tab.dataset.bookingEditTab));
        });

        window.addEventListener('resize', updateFloorLayoutScale);

        modal.querySelectorAll('[data-booking-edit-guest-step]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!guestInput) {
                    return;
                }

                const step = Number(button.dataset.bookingEditGuestStep || 0);
                const min = Number(guestInput.min || 1);
                const current = Number(guestInput.value || min);
                const next = Math.max(min, current + step);
                guestInput.value = String(next);
                guestInput.dispatchEvent(new Event('input', { bubbles: true }));
                guestInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        const observer = new MutationObserver(() => {
            if (!modal.hidden) {
                setActiveTab('personal');
            }
        });

        observer.observe(modal, { attributes: true, attributeFilter: ['hidden'] });
        setActiveTab('personal');
    })();
</script>
