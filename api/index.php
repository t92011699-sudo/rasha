<?php
// index.php - الصفحة الرئيسية للمرضى
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عيادة دكتورة راشا - حجز موعد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Tahoma', sans-serif;
        }
        .booking-container {
            max-width: 900px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 0 5px;
            transition: all 0.3s;
        }
        .step.active {
            background: #667eea;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .time-slot {
            padding: 10px 20px;
            margin: 5px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        .time-slot:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }
        .time-slot.selected {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .time-slot.booked {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            direction: rtl;
        }
        .calendar-day {
            padding: 10px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .calendar-day:hover {
            background: #667eea;
            color: white;
        }
        .calendar-day.selected {
            background: #667eea;
            color: white;
        }
        .calendar-day.disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        .form-section {
            display: none;
        }
        .form-section.active {
            display: block;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="booking-container">
        <div class="header">
            <h1><i class="fas fa-stethoscope"></i> عيادة دكتورة راشا</h1>
            <p class="mb-0">احجز موعدك الآن بكل سهولة</p>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1">1. اختيار التاريخ</div>
            <div class="step" id="step2">2. اختيار الوقت</div>
            <div class="step" id="step3">3. إدخال البيانات</div>
            <div class="step" id="step4">4. تأكيد الحجز</div>
        </div>

        <!-- Step 1: Calendar -->
        <div class="form-section active" id="section1">
            <h4><i class="fas fa-calendar-alt"></i> اختر تاريخ الموعد</h4>
            <div id="calendar" class="calendar-grid mt-3"></div>
            <div class="text-center mt-3">
                <button class="btn btn-primary-custom" id="selectDateBtn" disabled>اختر الوقت <i class="fas fa-arrow-left"></i></button>
            </div>
        </div>

        <!-- Step 2: Time Slots -->
        <div class="form-section" id="section2">
            <h4><i class="fas fa-clock"></i> اختر الوقت المناسب</h4>
            <div id="timeSlots" class="mt-3"></div>
            <div class="text-center mt-3">
                <button class="btn btn-secondary me-2" onclick="goToStep(1)"><i class="fas fa-arrow-right"></i> السابق</button>
                <button class="btn btn-primary-custom" id="selectTimeBtn" disabled>أدخل البيانات <i class="fas fa-arrow-left"></i></button>
            </div>
        </div>

        <!-- Step 3: Patient Form -->
        <div class="form-section" id="section3">
            <h4><i class="fas fa-user"></i> أدخل بياناتك</h4>
            <form id="patientForm">
                <div class="mb-3">
                    <label class="form-label">الاسم الكامل</label>
                    <input type="text" class="form-control" id="patientName" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="tel" class="form-control" id="patientPhone" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">العمر</label>
                    <input type="number" class="form-control" id="patientAge" required>
                </div>
                <div class="text-center">
                    <button type="button" class="btn btn-secondary me-2" onclick="goToStep(2)"><i class="fas fa-arrow-right"></i> السابق</button>
                    <button type="submit" class="btn btn-primary-custom">تأكيد الحجز <i class="fas fa-check"></i></button>
                </div>
            </form>
        </div>

        <!-- Step 4: Confirmation -->
        <div class="form-section" id="section4">
            <div class="text-center">
                <i class="fas fa-check-circle" style="font-size: 80px; color: #28a745;"></i>
                <h3 class="mt-3">تم حجز الموعد بنجاح!</h3>
                <p>شكراً لك، سيتم تأكيد حجزك عبر رسالة نصية.</p>
                <button class="btn btn-primary-custom mt-3" onclick="resetBooking()">حجز موعد آخر</button>
            </div>
        </div>
    </div>

    <script>
        let selectedDate = null;
        let selectedTime = null;
        let bookings = [];
        let currentStep = 1;

        // Generate calendar for current month
        function generateCalendar() {
            const today = new Date();
            const year = today.getFullYear();
            const month = today.getMonth();
            
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            
            const calendar = document.getElementById('calendar');
            calendar.innerHTML = '';
            
            // Day names
            const dayNames = ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];
            dayNames.forEach(name => {
                const dayNameDiv = document.createElement('div');
                dayNameDiv.className = 'calendar-day font-weight-bold';
                dayNameDiv.textContent = name;
                calendar.appendChild(dayNameDiv);
            });
            
            // Empty days before first day
            for (let i = 0; i < firstDay.getDay(); i++) {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'calendar-day disabled';
                calendar.appendChild(emptyDiv);
            }
            
            // Days
            for (let day = 1; day <= daysInMonth; day++) {
                const dayDiv = document.createElement('div');
                dayDiv.className = 'calendar-day';
                dayDiv.textContent = day;
                
                const date = new Date(year, month, day);
                const todayDate = new Date();
                todayDate.setHours(0, 0, 0, 0);
                
                // Disable past days and Fridays (جمعة)
                if (date < todayDate || date.getDay() === 5) {
                    dayDiv.className += ' disabled';
                } else {
                    dayDiv.onclick = function() {
                        selectDate(year, month, day);
                    };
                }
                
                calendar.appendChild(dayDiv);
            }
        }

        function selectDate(year, month, day) {
            // Remove previous selection
            document.querySelectorAll('.calendar-day.selected').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Add selection
            const days = document.querySelectorAll('.calendar-day');
            const targetDate = new Date(year, month, day);
            days.forEach(dayEl => {
                if (parseInt(dayEl.textContent) === day && !dayEl.classList.contains('disabled')) {
                    dayEl.classList.add('selected');
                }
            });
            
            selectedDate = targetDate;
            document.getElementById('selectDateBtn').disabled = false;
        }

        // Load time slots for selected date
        function loadTimeSlots() {
            if (!selectedDate) return;
            
            const dateStr = selectedDate.toISOString().split('T')[0];
            
            fetch(`api/appointments.php?date=${dateStr}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.text().then(text => text ? JSON.parse(text) : []);
                })
                .then(data => {
                    bookings = data;
                    displayTimeSlots();
                })
                .catch(error => {
                    console.error('Error:', error);
                    displayTimeSlots(); // Show all slots if error
                });
        }

        function displayTimeSlots() {
            const container = document.getElementById('timeSlots');
            container.innerHTML = '';
            
            // Generate time slots from 9 AM to 9 PM
            const slots = [];
            for (let hour = 9; hour <= 20; hour++) {
                slots.push(`${hour.toString().padStart(2, '0')}:00`);
                slots.push(`${hour.toString().padStart(2, '0')}:30`);
            }
            
            // Remove last slot at 20:30 if needed
            if (slots[slots.length - 1] === '20:30') slots.pop();
            
            slots.forEach(time => {
                const slotDiv = document.createElement('div');
                slotDiv.className = 'time-slot';
                slotDiv.textContent = time;
                
                // Check if slot is booked
                const isBooked = bookings.some(booking => booking.time === time && booking.date === selectedDate.toISOString().split('T')[0]);
                
                if (isBooked) {
                    slotDiv.classList.add('booked');
                } else {
                    slotDiv.onclick = function() {
                        selectTime(time);
                    };
                }
                
                container.appendChild(slotDiv);
            });
        }

        function selectTime(time) {
            document.querySelectorAll('.time-slot.selected').forEach(el => {
                el.classList.remove('selected');
            });
            
            document.querySelectorAll('.time-slot').forEach(el => {
                if (el.textContent === time && !el.classList.contains('booked')) {
                    el.classList.add('selected');
                }
            });
            
            selectedTime = time;
            document.getElementById('selectTimeBtn').disabled = false;
        }

        // Navigation
        function goToStep(step) {
            currentStep = step;
            
            // Hide all sections
            document.querySelectorAll('.form-section').forEach(el => {
                el.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(`section${step}`).classList.add('active');
            
            // Update step indicators
            document.querySelectorAll('.step').forEach((el, index) => {
                const stepNum = index + 1;
                el.classList.remove('active', 'completed');
                if (stepNum === step) {
                    el.classList.add('active');
                } else if (stepNum < step) {
                    el.classList.add('completed');
                }
            });
        }

        // Reset booking
        function resetBooking() {
            selectedDate = null;
            selectedTime = null;
            document.getElementById('selectDateBtn').disabled = true;
            document.getElementById('selectTimeBtn').disabled = true;
            document.getElementById('patientForm').reset();
            goToStep(1);
            generateCalendar();
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            generateCalendar();
            
            document.getElementById('selectDateBtn').addEventListener('click', function() {
                loadTimeSlots();
                goToStep(2);
            });
            
            document.getElementById('selectTimeBtn').addEventListener('click', function() {
                goToStep(3);
            });
            
            document.getElementById('patientForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const name = document.getElementById('patientName').value;
                const phone = document.getElementById('patientPhone').value;
                const age = document.getElementById('patientAge').value;
                
                if (!name || !phone || !age) {
                    alert('الرجاء ملء جميع الحقول');
                    return;
                }
                
                const bookingData = {
                    name: name,
                    phone: phone,
                    age: age,
                    date: selectedDate.toISOString().split('T')[0],
                    time: selectedTime,
                    status: 'pending'
                };
                
                fetch('api/appointments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(bookingData)
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.text().then(text => text ? JSON.parse(text) : {});
                })
                .then(data => {
                    if (data.status === 'success') {
                        goToStep(4);
                    } else {
                        alert('حدث خطأ في الحجز، يرجى المحاولة مرة أخرى');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ في الحجز، يرجى المحاولة مرة أخرى');
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>