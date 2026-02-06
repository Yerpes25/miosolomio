(function () {
    const apiUrl = window.CHEAdmin?.apiUrl;

    const calTitleEl = document.getElementById('che-sede-cal-title');
    const calGridEl  = document.getElementById('che-sede-cal-grid');
    const calPrevBtn = document.getElementById('che-sede-cal-prev');
    const calNextBtn = document.getElementById('che-sede-cal-next');
    const calToggleBtn = document.getElementById('che-sede-cal-toggle');

    if (!calGridEl || !calTitleEl) return;

    let calCurrentDate   = new Date();
    let calCurrentSedeId = null;
    let sedeHolidays     = {}; // { [sedeId]: ['YYYY-MM-DD', ...] }
    let isEditing        = false;

    function renderSedeCalendar() {
        if (!calCurrentSedeId) return;

        calGridEl.innerHTML = '';

        const year  = calCurrentDate.getFullYear();
        const month = calCurrentDate.getMonth();

        const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        calTitleEl.textContent = `${monthNames[month]} ${year}`;

        const dayNames = ['L','M','X','J','V','S','D'];
        dayNames.forEach(d => {
            const el = document.createElement('div');
            el.className = 'che-sede-cal-day-name';
            el.textContent = d;
            calGridEl.appendChild(el);
        });

        const firstDay      = new Date(year, month, 1);
        const startWeekday  = (firstDay.getDay() + 6) % 7;
        const daysInMonth   = new Date(year, month + 1, 0).getDate();
        const holidaysForSede = sedeHolidays[calCurrentSedeId] || [];

        for (let i = 0; i < startWeekday; i++) {
            const empty = document.createElement('div');
            empty.className = 'che-sede-cal-day che-sede-cal-day--empty';
            calGridEl.appendChild(empty);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            // Construir fecha en formato YYYY-MM-DD directamente para evitar problemas de zona horaria
            const monthStr = String(month + 1).padStart(2, '0');
            const dayStr = String(day).padStart(2, '0');
            const dateStr = `${year}-${monthStr}-${dayStr}`;

            const cell = document.createElement('div');
            cell.className = 'che-sede-cal-day';
            cell.textContent = String(day);
            cell.dataset.date = dateStr;

            if (holidaysForSede.includes(dateStr)) {
                cell.classList.add('che-sede-cal-day--holiday');
            }

            calGridEl.appendChild(cell);
        }
    }

    if (calPrevBtn) {
        calPrevBtn.addEventListener('click', () => {
            calCurrentDate.setMonth(calCurrentDate.getMonth() - 1);
            renderSedeCalendar();
        });
    }

    if (calNextBtn) {
        calNextBtn.addEventListener('click', () => {
            calCurrentDate.setMonth(calCurrentDate.getMonth() + 1);
            renderSedeCalendar();
        });
    }

    if (calGridEl) {
        calGridEl.addEventListener('click', (e) => {
            if (!isEditing) return; // solo se puede editar en modo edición
            const dayEl = e.target.closest('.che-sede-cal-day');
            if (!dayEl || dayEl.classList.contains('che-sede-cal-day--empty') || !calCurrentSedeId) return;

            const dateStr = dayEl.dataset.date;
            if (!dateStr) return;

            const list = sedeHolidays[calCurrentSedeId] || [];
            const idx  = list.indexOf(dateStr);

            if (idx === -1) {
                list.push(dateStr);
                dayEl.classList.add('che-sede-cal-day--holiday');
            } else {
                list.splice(idx, 1);
                dayEl.classList.remove('che-sede-cal-day--holiday');
            }
            sedeHolidays[calCurrentSedeId] = list;

            // aquí más adelante podemos hacer fetch para guardar en la API
        });
    }
    /* Guardar cambios de dias festivos */
    if (calToggleBtn) {
        calToggleBtn.addEventListener('click', async () => {
            // Entrar en modo edición
            if (!isEditing) {
                isEditing = true;
                calToggleBtn.textContent = 'Guardar cambios';
                calToggleBtn.classList.add('is-editing');
                return;
            }

            // Salir de modo edición y guardar
            isEditing = false;
            calToggleBtn.textContent = 'Añadir días';
            calToggleBtn.classList.remove('is-editing');

            const holidays = sedeHolidays[calCurrentSedeId] || [];

            // POST a la API para guardar
            try {
                const res  = await fetch(apiUrl + 'admin/sedes/' + calCurrentSedeId + '/festivos', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.cheAjax?.nonce || ''
                    },
                    body: JSON.stringify({ dias: holidays })
                });

                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'Error al guardar festivos');
                }
            } catch (e) {
                alert(e.message || 'Error al guardar festivos');
            }
        });
    }

    // Exponer funciones al resto del admin
    window.CHEAdmin = window.CHEAdmin || {};
    window.CHEAdmin.sedeCalendar = {
        async setSede(sedeId) {
            calCurrentSedeId = String(sedeId);
            calCurrentDate   = new Date();

            // Cargar festivos de la sede desde la API
            try {
                const res  = await fetch(apiUrl + 'admin/sedes/' + calCurrentSedeId + '/festivos', {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': window.cheAjax?.nonce || ''
                    }
                });
                const data = await res.json();

                if (res.ok && data.success && Array.isArray(data.dias)) {
                    sedeHolidays[calCurrentSedeId] = data.dias;
                } else {
                    sedeHolidays[calCurrentSedeId] = [];
                }
            } catch (e) {
                console.error('Error cargando festivos de sede', sedeId, e);
                sedeHolidays[calCurrentSedeId] = [];
            }

            renderSedeCalendar();
        },
        // por si luego quieres inicializar festivos desde la API
        setHolidays: function (sedeId, datesArray) {
            sedeHolidays[String(sedeId)] = Array.isArray(datesArray) ? datesArray : [];
            if (String(sedeId) === calCurrentSedeId) {
                renderSedeCalendar();
            }
        }
    };
})();
