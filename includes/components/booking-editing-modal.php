<?php
/**
 * Reusable admin booking editing modal.
 *
 * Optional variables before include:
 *   $bookingEditModalId = 'bookingEditModal';
 *   $bookingEditFormId = 'bookingEditForm';
 *   $bookingEditTitle = 'Edit Booking';
 *   $bookingEditTables = [
 *       ['table_id' => 1, 'table_number' => '1', 'area_name' => 'Main', 'capacity' => 4],
 *   ];
 */
$bookingEditModalId = $bookingEditModalId ?? 'bookingEditModal';
$bookingEditFormId = $bookingEditFormId ?? 'bookingEditForm';
$bookingEditTitle = $bookingEditTitle ?? 'Edit Booking';
$bookingEditTables = $bookingEditTables ?? [];
$bookingEditStatuses = $bookingEditStatuses ?? [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'completed' => 'Completed',
    'no_show' => 'No-show',
    'cancelled' => 'Cancelled',
];
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
        width: min(640px, 100%);
        max-height: calc(100vh - 40px);
        overflow: hidden;
        background: var(--dm-surface);
        border: 1px solid var(--dm-border);
        border-radius: var(--dm-radius-md);
        box-shadow: var(--dm-shadow-md);
    }

    .booking-edit-header,
    .booking-edit-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid var(--dm-border);
    }

    .booking-edit-footer {
        border-top: 1px solid var(--dm-border);
        border-bottom: none;
    }

    .booking-edit-title {
        margin: 0;
        color: var(--dm-text);
        font-size: 16px;
        font-weight: 700;
    }

    .booking-edit-close,
    .booking-edit-danger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--dm-border);
        border-radius: var(--dm-radius-sm);
        cursor: pointer;
    }

    .booking-edit-close {
        width: 34px;
        height: 34px;
        background: var(--dm-surface-muted);
        color: var(--dm-text);
    }

    .booking-edit-body {
        display: grid;
        gap: 18px;
        max-height: calc(100vh - 180px);
        overflow-y: auto;
        padding: 18px;
    }

    .booking-edit-section {
        display: grid;
        gap: 12px;
    }

    .booking-edit-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: var(--dm-text);
        font-size: 13px;
        font-weight: 800;
    }

    .booking-edit-grid {
        display: grid;
        grid-template-columns: 1.35fr 0.85fr 0.7fr;
        gap: 14px;
    }

    .booking-edit-field {
        display: grid;
        gap: 6px;
        min-width: 0;
    }

    .booking-edit-field.full { grid-column: 1 / -1; }
    .booking-edit-field.wide { grid-column: span 2; }

    .booking-edit-field label {
        color: var(--dm-text-muted);
        font-size: 12px;
        font-weight: 700;
    }

    .booking-edit-input,
    .booking-edit-select,
    .booking-edit-textarea {
        width: 100%;
        min-width: 0;
        border: 1px solid var(--dm-border);
        border-radius: var(--dm-radius-sm);
        background: var(--dm-surface);
        color: var(--dm-text);
        padding: 10px 12px;
        font-size: 14px;
    }

    .booking-edit-input.with-icon,
    .booking-edit-select.with-icon {
        padding-left: 34px;
    }

    .booking-edit-control {
        position: relative;
    }

    .booking-edit-control i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--dm-text-muted);
        font-size: 13px;
        pointer-events: none;
    }

    .booking-edit-textarea {
        min-height: 96px;
        resize: vertical;
    }

    .booking-edit-error {
        display: none;
        padding: 10px 12px;
        border: 1px solid var(--dm-danger-border);
        border-radius: var(--dm-radius-sm);
        background: var(--dm-danger-bg);
        color: var(--dm-danger-text);
        font-size: 13px;
    }

    .booking-edit-error.is-visible { display: block; }

    .booking-edit-actions,
    .booking-edit-primary-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .booking-edit-danger {
        min-height: 38px;
        padding: 0 13px;
        background: var(--dm-danger-bg);
        border-color: var(--dm-danger-border);
        color: var(--dm-danger-text);
        font-size: 13px;
        font-weight: 700;
        gap: 8px;
    }

    @media (max-width: 720px) {
        .booking-edit-grid {
            grid-template-columns: 1fr 1fr;
        }

        .booking-edit-field.wide {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 540px) {
        .booking-edit-grid {
            grid-template-columns: 1fr;
        }

        .booking-edit-footer {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .booking-edit-actions,
        .booking-edit-primary-actions,
        .booking-edit-danger {
            width: 100%;
        }
    }
</style>

<div class="booking-edit-modal" id="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>" hidden>
    <div class="booking-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Title">
        <form id="<?php echo htmlspecialchars($bookingEditFormId, ENT_QUOTES, 'UTF-8'); ?>" data-booking-edit-form>
            <div class="booking-edit-header">
                <h2 class="booking-edit-title" id="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Title"><?php echo htmlspecialchars($bookingEditTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
                <button type="button" class="booking-edit-close" data-booking-edit-close aria-label="Close editor"><i class="fa fa-xmark"></i></button>
            </div>

            <div class="booking-edit-body">
                <div class="booking-edit-error" data-booking-edit-error></div>
                <input type="hidden" name="booking_id" data-booking-edit-id>
                <input type="hidden" name="end_time" data-booking-edit-end>

                <section class="booking-edit-section">
                    <h3 class="booking-edit-section-title"><i class="fa fa-calendar-check"></i> Booking</h3>
                    <div class="booking-edit-grid">
                        <div class="booking-edit-field wide">
                            <label for="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Name">Name</label>
                            <div class="booking-edit-control">
                                <i class="fa fa-user"></i>
                                <input class="booking-edit-input with-icon" type="text" id="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Name" name="customer_name" data-booking-edit-name required>
                            </div>
                        </div>

                        <div class="booking-edit-field">
                            <label for="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Guests">Guests</label>
                            <div class="booking-edit-control">
                                <i class="fa fa-users"></i>
                                <input class="booking-edit-input with-icon" type="number" id="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Guests" name="number_of_guests" min="1" data-booking-edit-guests required>
                            </div>
                        </div>

                        <div class="booking-edit-field">
                            <label for="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Date">Date</label>
                            <div class="booking-edit-control">
                                <i class="fa fa-calendar-day"></i>
                                <input class="booking-edit-input with-icon" type="date" id="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Date" name="booking_date" data-booking-edit-date required>
                            </div>
                        </div>

                        <div class="booking-edit-field">
                            <label for="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Start">Time</label>
                            <div class="booking-edit-control">
                                <i class="fa fa-clock"></i>
                                <input class="booking-edit-input with-icon" type="time" id="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Start" name="start_time" min="10:00" max="21:30" step="1800" data-booking-edit-start required>
                            </div>
                        </div>

                        <div class="booking-edit-field">
                            <label for="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Status">Status</label>
                            <select class="booking-edit-select" id="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Status" name="status" data-booking-edit-status>
                                <?php foreach ($bookingEditStatuses as $statusValue => $statusLabel): ?>
                                    <option value="<?php echo htmlspecialchars((string) $statusValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $statusLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="booking-edit-field wide">
                            <label for="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Table">Table</label>
                            <select class="booking-edit-select" id="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Table" name="table_id" data-booking-edit-table>
                                <option value="" data-label="No table">No table</option>
                                <?php foreach ($bookingEditTables as $table): ?>
                                    <?php $tableLabel = 'Table ' . (string) ($table['table_number'] ?? ''); ?>
                                    <option value="<?php echo (int) ($table['table_id'] ?? 0); ?>" data-label="<?php echo htmlspecialchars($tableLabel, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($tableLabel . ' · ' . (string) ($table['area_name'] ?? 'Dining room') . ' · ' . (int) ($table['capacity'] ?? 0) . ' seats', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="booking-edit-field">
                            <label for="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Email">Email</label>
                            <div class="booking-edit-control">
                                <i class="fa fa-envelope"></i>
                                <input class="booking-edit-input with-icon" type="email" id="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Email" name="customer_email" data-booking-edit-email>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="booking-edit-section">
                    <h3 class="booking-edit-section-title"><i class="fa fa-note-sticky"></i> Notes</h3>
                    <div class="booking-edit-field full">
                        <label for="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Notes">Notes</label>
                        <textarea class="booking-edit-textarea" id="<?php echo htmlspecialchars($bookingEditModalId, ENT_QUOTES, 'UTF-8'); ?>Notes" name="special_request" data-booking-edit-notes placeholder="Optional notes"></textarea>
                    </div>
                </section>
            </div>

            <div class="booking-edit-footer">
                <div class="booking-edit-actions">
                    <button type="button" class="booking-edit-danger" data-booking-edit-delete><i class="fa fa-trash"></i>Delete</button>
                </div>
                <div class="booking-edit-primary-actions">
                    <button type="button" class="ui-button ui-button-ghost" data-booking-edit-cancel>Cancel</button>
                    <button type="submit" class="ui-button ui-button-primary" data-booking-edit-save>Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
