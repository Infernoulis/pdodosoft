<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'db.php';
require 'header.php';
if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit;
}


// Fetch data
$company_id = $_SESSION['company_id'] ?? 0;

// Fetch clients belonging to this company only
$clients = $pdo->prepare("SELECT id, fullname FROM clients WHERE company_id = ? ORDER BY fullname");
$clients->execute([$company_id]);
$clients = $clients->fetchAll();

// Fetch pricelist belonging to this company only
$pricelist = $pdo->prepare("SELECT id, description, duration, price FROM pricelist WHERE company_id = ? ORDER BY description");
$pricelist->execute([$company_id]);
$services = $pricelist->fetchAll();

// Fetch employees belonging to this company only
$employees_stmt = $pdo->prepare("SELECT id, firstname FROM employees WHERE status = 'enabled' AND company_id = ? ORDER BY id");
$employees_stmt->execute([$company_id]);
$employees = $employees_stmt->fetchAll();

// Fetch appointments belonging to this company only
$appointments_stmt = $pdo->prepare("SELECT a.*, c.fullname, p.description AS service_description 
    FROM appointments a 
    JOIN clients c ON a.client_id = c.id 
    JOIN pricelist p ON a.service_id = p.id
    WHERE a.company_id = ?");
$appointments_stmt->execute([$_SESSION['company_id']]);
$appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);



// Stats
$totalAppointments = count($appointments);
$totalClients = count($clients);
$today = date('Y-m-d');
$appointmentsToday = array_filter($appointments, fn($a) => strpos($a['start'], $today) === 0);

// Calculate total appointment hours for the current month
$totalDurationSeconds = 0;
$currentYearMonth = date('Y-m');
foreach ($appointments as $a) {
    if (strpos($a['start'], $currentYearMonth) === 0) {
        $start = new DateTime($a['start']);
        if (!empty($a['end'])) {
            $end = new DateTime($a['end']);
        } else {
            // Default duration 1 hour if no end time
            $end = clone $start;
            $end->modify('+1 hour');
        }
        $totalDurationSeconds += $end->getTimestamp() - $start->getTimestamp();
    }
}
$totalDurationHours = round($totalDurationSeconds / 3600, 2);

?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/luxon@3.4.4/build/global/luxon.min.js"></script>
<!-- Replace old FullCalendar includes with this Scheduler bundle -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.17/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.17/index.global.min.js"></script>
<!-- Then add locales -->
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.17/locales-all.global.min.js"></script>
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Greek Theme (Optional) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">




    <style>
        .logo-img { height: 100px; }
        .fc-event:hover { cursor: pointer; background-color: #e9ecef !important; }
        .flatpickr-confirm {
  background-color: #0d6efd !important; /* Bootstrap primary */
  color: white !important;
  font-weight: bold;
  font-size: 14px;
  padding: 6px 12px;
  border-radius: 6px;
  border: none;
  margin-top: 5px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
  cursor: pointer;
}

.flatpickr-confirm:hover {
  background-color: #0b5ed7 !important;
}
</style>
    
</head>
<body class="p-4 bg-light">
<div class="container">

    <!-- Header -->
    

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="mainTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#appointments">Ραντεβού</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#clients">Πελατολόγιο</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tools">Εργαλεία</button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- Appointments Tab -->
        <div class="tab-pane fade show active" id="appointments">
            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>Σύνολο Ραντεβού</h5><p class="display-6"><?= $totalAppointments ?></p></div></div></div>
                <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>Πελάτες</h5><p class="display-6"><?= $totalClients ?></p></div></div></div>
                <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>Ραντεβού Σήμερα</h5><p class="display-6"><?= count($appointmentsToday) ?></p></div></div></div>
                <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>Συνολικές Ώρες Μηνός</h5><p class="display-6"><?= $totalDurationHours ?></p></div></div></div>
            </div>

            <!-- Calendar -->
            <div class="d-flex justify-content-between mb-3">
                <h4>Ημερολόγιο</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#appointmentModal" onclick="clearAppointmentForm()">+ Νέο Ραντεβού (ανοιχτό) </button>
            </div>
            <div id="calendar"></div>
    

        </div>

        <!-- Clients Tab -->
        <div class="tab-pane fade" id="clients">
            <div class="d-flex justify-content-between mb-3">
                <h4>Λίστα Πελατών</h4>
                <a href="add_client.php" class="btn btn-success">+ Προσθήκη</a>
            </div>
            <ul class="list-group">
                <?php foreach ($clients as $client): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($client['fullname']) ?>
                        <a href="client_dashboard.php?client_id=<?= $client['id'] ?>" class="btn btn-sm btn-primary">Άνοιγμα</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Tools Tab -->
        <div class="tab-pane fade" id="tools">
            <div class="d-flex justify-content-between mb-3">
                <h4>Εργαλεία</h4>
                <!-- Add tools or links here -->
            </div>
               <ul class="list-group">
       			<li class="list-group-item">
    				<a href="employees.php" class="text-decoration-none">👤 Διαχείριση Υπαλλήλων</a>
				</li>
                <li class="list-group-item">
             		<a href="add_edit_pricelist.php" class="text-decoration-none">🏷️Τιμοκατάλογος</a>
        		</li>
                                <li class="list-group-item">
             		<a href="" class="text-decoration-none">💶 Ταμείο</a>
        		</li>
                                <li class="list-group-item">
             		<a href="" class="text-decoration-none">📊 Στατιστικά</a>
        		</li>
        <!-- You can add more tools below if needed -->
    </ul>
</div>
        </div>

    </div>
</div>

<!-- Appointment Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="appointmentForm" method="post"  class="modal-content">
            <input type="hidden" name="appointment_id" id="appointment_id">
            <div class="modal-header">
                <h5 class="modal-title">Ραντεβού</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
            </div>
            <div class="modal-body">

                <!-- 🔻 Error placeholder -->
                <div id="modalError" class="alert alert-danger d-none"></div>

                <div class="mb-3">
                    <label for="employee_id" class="form-label">Υπάλληλος</label>
                    <select name="employee_id" id="employee_id" class="form-select" required>
                        <option value="">-- Επιλέξτε --</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= $employee['id'] ?>"><?= htmlspecialchars($employee['firstname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="client_id" class="form-label">Ασθενής</label>
                    <select name="client_id" id="client_id" class="form-select" required>
                        <option value="">-- Επιλέξτε --</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Ημερομηνία & Ώρα Έναρξης</label>

			<input type="datetime-local" class="form-control" id="start" name="start" required>

                </div>

                <div class="mb-3" style="display: none;">
                    <label class="form-label">Ημερομηνία & Ώρα Λήξης</label>
           <input type="datetime-local" class="form-control" id="end" name="end" required>
                        
                </div>

                <div class="mb-3">
                    <label for="service_id" class="form-label">Υπηρεσία</label>
                    <select name="service_id" id="service_id" class="form-select" required>
                        <option value="">-- Επιλέξτε --</option>
                        <?php foreach ($services as $service): ?>
                            <option 
                                value="<?= $service['id'] ?>" 
                                data-duration="<?= $service['duration'] ?>">
                                <?= htmlspecialchars($service['description']) ?> (<?= $service['duration'] ?>') - <?= number_format($service['price'], 2) ?>€
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Σημειώσεις</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
                </div>

            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Αποθήκευση</button>
                <button type="button" class="btn btn-danger" id="deleteBtn" style="display: none;">Διαγραφή</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Άκυρο</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('deleteBtn').addEventListener('click', function () {
    const appointmentId = document.getElementById('appointment_id').value;
    if (appointmentId && confirm("Σίγουρα θέλετε να διαγράψετε αυτό το ραντεβού;")) {
        window.location.href = "delete_appointment.php?id=" + appointmentId;
    }
});

function clearAppointmentForm() {
    const form = document.querySelector('#appointmentModal form');

    // Reset form fields
    form.reset();

    // Clear hidden ID and end input explicitly
    document.getElementById('appointment_id').value = '';
    document.getElementById('end').value = '';

    // Hide delete button
    document.getElementById('deleteBtn').style.display = 'none';

    // Remove previous change listeners from service_id to avoid duplicates
    const newService = document.getElementById('service_id').cloneNode(true);
    const oldService = document.getElementById('service_id');
    oldService.parentNode.replaceChild(newService, oldService);

    // Add fresh listener for duration → auto-fill end time
    newService.addEventListener('change', function () {
        const selectedOption = this.options[this.selectedIndex];
        const duration = parseInt(selectedOption.getAttribute('data-duration'));

        const startInput = document.getElementById('start');
        const endInput = document.getElementById('end');
        const startTime = startInput.value;

        if (startTime && duration) {
            const startDate = new Date(startTime);
            startDate.setMinutes(startDate.getMinutes() + duration);

            const pad = n => n.toString().padStart(2, '0');
            const endStr = startDate.getFullYear() + '-' +
                        pad(startDate.getMonth() + 1) + '-' +
                        pad(startDate.getDate()) + 'T' +
                        pad(startDate.getHours()) + ':' +
                        pad(startDate.getMinutes());

            endInput.value = endStr;
        }
    });

	document.getElementById('employee_id').value = '';

}
function showErrorInModal(message) {
    let alertBox = document.getElementById('modalError');
    if (!alertBox) {
        const modalBody = document.querySelector('#appointmentModal .modal-body');
        alertBox = document.createElement('div');
        alertBox.id = 'modalError';
        alertBox.className = 'alert alert-danger';
        modalBody.prepend(alertBox);
    }
    alertBox.textContent = message;
}

function formatDateTime(date) {
    return new Intl.DateTimeFormat('el-GR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false
    }).format(date).replace(',', '');
}

function toInputValue(date) {
    const DateTime = luxon.DateTime;
    const athensTime = DateTime.fromISO(date.toISOString(), { zone: 'utc' }).setZone('Europe/Athens');
    return athensTime.toFormat("yyyy-MM-dd'T'HH:mm");
}

let calendar;
document.addEventListener('DOMContentLoaded', function () {

    calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',  // Open source key
        initialView: 'resourceTimeGridDay',
        selectable: true,
        selectMirror: true,
        locale: 'el',
        timeZone: 'local',
        nowIndicator: true,
        allDaySlot: false,
        editable: false,
        height: 'auto',
        slotMinTime: "08:00:00",
        slotMaxTime: "22:00:00",
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'resourceTimeGridDay,resourceTimeGridWeek,dayGridMonth'
        },
        buttonText: {
            today: 'Σήμερα',
            month: 'Μήνας',
         	week: 'Εβδομάδα',
            day: 'Ημέρα'
        },

        // 👤 Employee columns
        resources: <?= json_encode(array_map(function($e) {
            return [
                'id' => $e['id'],
                'title' => $e['firstname']
            ];
        }, $employees), JSON_UNESCAPED_UNICODE) ?>,

        // 📅 Appointments
        events: <?= json_encode(array_map(function($a) {
            return [
                'id' => $a['id'],
                'title' => $a['fullname'] . ' - ' . $a['service_description'],
                'start' => $a['start'],
                'end' => $a['end'] ?: null,
                'resourceId' => $a['employee_id'],
                'extendedProps' => [
                    'client_id' => $a['client_id'],
                    'notes' => $a['notes'],
                    'service_id' => $a['service_id'],
                    'employee_id' => $a['employee_id'],
                	'service_description' => $a['service_description'] 
                ]
            ];
        }, $appointments), JSON_UNESCAPED_UNICODE) ?>,

eventClick: function(info) {
    clearAppointmentForm();

    const e = info.event;
    const props = e.extendedProps;

    const toInputValue = (date) => {
        const pad = (n) => n.toString().padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    document.getElementById('appointment_id').value = e.id;
    document.getElementById('client_id').value = props.client_id || '';
    document.getElementById('start').value = e.start ? toInputValue(new Date(e.start)) : '';
    document.getElementById('end').value = e.end ? toInputValue(new Date(e.end)) : '';
    document.getElementById('notes').value = props.notes || '';
    document.getElementById('service_id').value = props.service_id || '';
    document.getElementById('employee_id').value = props.employee_id || '';
    document.getElementById('deleteBtn').style.display = 'inline-block';

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('appointmentModal'));
    modal.show();
},

select: function(info) {
    clearAppointmentForm();

    const toInputValue = (date) => {
        const pad = (n) => n.toString().padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    document.getElementById('start').value = toInputValue(info.start);
    document.getElementById('end').value = toInputValue(info.end);

    if (info.resource) {
        document.getElementById('employee_id').value = info.resource.id;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('appointmentModal'));
    modal.show();
}

    });

    calendar.render();
});
</script>

<script>
document.getElementById('appointmentForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    fetch('save_appointment.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Σφάλμα αποθήκευσης');
        }

        // Hide modal
        const modalEl = document.getElementById('appointmentModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) {
            modalInstance.hide();
        }

        // 🔁 Brute-force refresh the page
        window.location.reload();
    })
    .catch(error => {
        showErrorInModal(error.message);
    });
});
</script>

                                                                                  <script>
document.getElementById('appointmentModal').addEventListener('hidden.bs.modal', () => {
    document.getElementById('appointmentForm').reset();
    document.getElementById('appointment_id').value = '';
    document.getElementById('modalError').classList.add('d-none');
});
</script>

                                                                                  <script>
document.getElementById('appointmentForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const errorBox = document.getElementById('modalError');
    const modalElement = document.getElementById('appointmentModal');
    const deleteBtn = document.getElementById('deleteBtn');

    // Clear previous error
    errorBox.textContent = '';
    errorBox.classList.add('d-none');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            const errorText = await response.text();
            errorBox.textContent = errorText || 'Προέκυψε σφάλμα κατά την αποθήκευση.';
            errorBox.classList.remove('d-none');
            return;
        }

        // ✅ Success — close modal and refresh calendar
        const modal = bootstrap.Modal.getInstance(modalElement);
        modal.hide();

        if (window.calendar) {
            window.calendar.refetchEvents();
        }

        form.reset();
        deleteBtn.style.display = 'none';

    } catch (err) {
        console.error(err);
        errorBox.textContent = 'Αποτυχία σύνδεσης με τον διακομιστή.';
        errorBox.classList.remove('d-none');
    }
});
</script>

<script>
flatpickr("#start", {
    enableTime: true,
    dateFormat: "d/m/Y H:i",
    locale: "gr",
    time_24hr: true,
    allowInput: true,
    plugins: [
        new confirmDatePlugin({
            confirmText: "OK",
            showAlways: false,
            theme: "material_blue"
        })
    ]
});

flatpickr("#end", {
    enableTime: true,
    dateFormat: "d/m/Y H:i",
    locale: "gr",
    time_24hr: true,
    allowInput: true,
    plugins: [
        new confirmDatePlugin({
            confirmText: "OK",
            showAlways: false,
            theme: "material_blue"
        })
    ]
});
</script>
                                                                                  

<script>
function updateEndTimeFromDuration() {
    const serviceSelect = document.getElementById('service_id');
    const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
    const duration = parseInt(selectedOption.getAttribute('data-duration'));

    const startInput = document.getElementById('start');
    const endInput = document.getElementById('end');
    const startTime = startInput.value;

    if (startTime && duration) {
        const startDate = new Date(startTime);
        startDate.setMinutes(startDate.getMinutes() + duration);

        const pad = n => n.toString().padStart(2, '0');
        const endStr = startDate.getFullYear() + '-' +
                       pad(startDate.getMonth() + 1) + '-' +
                       pad(startDate.getDate()) + 'T' +
                       pad(startDate.getHours()) + ':' +
                       pad(startDate.getMinutes());

        endInput.value = endStr;
    }
}

// Trigger when service is changed
document.getElementById('service_id').addEventListener('change', updateEndTimeFromDuration);

// Trigger when start time is changed
document.getElementById('start').addEventListener('change', updateEndTimeFromDuration);
</script>
<script>
eventOverlap: function(stillEvent, movingEvent) {
  // Prevent overlap if same employee (resourceId)
  return stillEvent.resourceId !== movingEvent.resourceId;
},
</script>

</body>
</html>
