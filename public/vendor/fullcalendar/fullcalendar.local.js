(function () {
    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function parseDate(value) {
        return value ? new Date(value) : new Date();
    }

    function dateKey(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function addDays(date, days) {
        const next = new Date(date);
        next.setDate(next.getDate() + days);
        return next;
    }

    function startOfWeek(date) {
        const next = new Date(date);
        next.setDate(next.getDate() - next.getDay());
        next.setHours(0, 0, 0, 0);
        return next;
    }

    function monthName(date) {
        return date.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    }

    function shortDate(date) {
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    class CalendarEvent {
        constructor(raw) {
            this.id = raw.id;
            this.title = raw.title || 'Event';
            this.start = parseDate(raw.start);
            this.allDay = Boolean(raw.allDay);
            this.backgroundColor = raw.backgroundColor || '#0e7490';
            this.borderColor = raw.borderColor || this.backgroundColor;
            this.extendedProps = raw.extendedProps || {};
        }
    }

    class Calendar {
        constructor(element, options) {
            this.element = element;
            this.options = options || {};
            this.currentDate = new Date();
            this.currentView = this.options.initialView || 'dayGridMonth';
            this.events = (this.options.events || []).map((event) => new CalendarEvent(event));
        }

        render() {
            this.element.classList.add('fc');
            this.draw();
        }

        draw() {
            this.element.innerHTML = '';
            this.element.appendChild(this.toolbar());
            this.element.appendChild(this.view());
        }

        toolbar() {
            const toolbar = document.createElement('div');
            toolbar.className = 'fc-toolbar';

            const left = document.createElement('div');
            left.className = 'fc-button-group';
            left.appendChild(this.button('Prev', () => this.move(-1)));
            left.appendChild(this.button('Next', () => this.move(1)));
            left.appendChild(this.button('Today', () => {
                this.currentDate = new Date();
                this.draw();
            }, true));

            const title = document.createElement('div');
            title.className = 'fc-toolbar-title';
            title.textContent = this.title();

            const right = document.createElement('div');
            right.className = 'fc-button-group';
            [
                ['dayGridMonth', 'Month'],
                ['timeGridWeek', 'Week'],
                ['timeGridDay', 'Day'],
            ].forEach(([view, label]) => {
                const button = this.button(label, () => {
                    this.currentView = view;
                    this.draw();
                }, true);
                if (this.currentView === view) {
                    button.classList.add('fc-button-active');
                }
                right.appendChild(button);
            });

            toolbar.appendChild(left);
            toolbar.appendChild(title);
            toolbar.appendChild(right);

            return toolbar;
        }

        button(label, handler, primary) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = primary ? 'fc-button fc-button-primary' : 'fc-button';
            button.textContent = label;
            button.addEventListener('click', handler);
            return button;
        }

        title() {
            if (this.currentView === 'dayGridMonth') {
                return monthName(this.currentDate);
            }

            if (this.currentView === 'timeGridWeek') {
                const start = startOfWeek(this.currentDate);
                const end = addDays(start, 6);
                return shortDate(start) + ' - ' + shortDate(end) + ', ' + end.getFullYear();
            }

            return this.currentDate.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        }

        move(direction) {
            if (this.currentView === 'dayGridMonth') {
                this.currentDate.setMonth(this.currentDate.getMonth() + direction);
            } else if (this.currentView === 'timeGridWeek') {
                this.currentDate.setDate(this.currentDate.getDate() + (direction * 7));
            } else {
                this.currentDate.setDate(this.currentDate.getDate() + direction);
            }
            this.draw();
        }

        view() {
            if (this.currentView === 'dayGridMonth') {
                return this.monthView();
            }

            return this.listView(this.currentView === 'timeGridWeek' ? 7 : 1);
        }

        monthView() {
            const wrapper = document.createElement('div');
            wrapper.className = 'fc-view fc-grid';
            ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach((day) => {
                const header = document.createElement('div');
                header.className = 'fc-day-header';
                header.textContent = day;
                wrapper.appendChild(header);
            });

            const first = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
            const start = startOfWeek(first);

            for (let index = 0; index < 42; index += 1) {
                const date = addDays(start, index);
                const cell = document.createElement('div');
                cell.className = 'fc-day' + (date.getMonth() === this.currentDate.getMonth() ? '' : ' fc-day-muted');

                const number = document.createElement('span');
                number.className = 'fc-day-number';
                number.textContent = date.getDate();
                cell.appendChild(number);

                this.eventsForDate(date).slice(0, 4).forEach((event) => cell.appendChild(this.eventNode(event)));
                wrapper.appendChild(cell);
            }

            return wrapper;
        }

        listView(days) {
            const wrapper = document.createElement('div');
            wrapper.className = 'fc-view fc-list';
            const start = days === 7 ? startOfWeek(this.currentDate) : new Date(this.currentDate);

            for (let index = 0; index < days; index += 1) {
                const date = addDays(start, index);
                const header = document.createElement('div');
                header.className = 'fc-list-day';
                header.textContent = date.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' });
                wrapper.appendChild(header);

                const dayEvents = this.eventsForDate(date);
                if (!dayEvents.length) {
                    const empty = document.createElement('div');
                    empty.className = 'fc-list-event';
                    empty.textContent = 'No events';
                    wrapper.appendChild(empty);
                }

                dayEvents.forEach((event) => {
                    const row = document.createElement('div');
                    row.className = 'fc-list-event';
                    row.appendChild(this.textNode('fc-list-event-time', event.allDay ? 'All day' : event.start.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })));
                    row.appendChild(this.textNode('fc-list-event-title', event.title));
                    const dot = document.createElement('span');
                    dot.className = 'fc-list-event-dot';
                    dot.style.backgroundColor = event.backgroundColor;
                    row.appendChild(dot);
                    row.addEventListener('click', () => this.emitEventClick(event));
                    wrapper.appendChild(row);
                });
            }

            return wrapper;
        }

        textNode(className, text) {
            const node = document.createElement('span');
            node.className = className;
            node.textContent = text;
            return node;
        }

        eventsForDate(date) {
            const key = dateKey(date);
            return this.events.filter((event) => dateKey(event.start) === key);
        }

        eventNode(event) {
            const node = document.createElement('button');
            node.type = 'button';
            node.className = 'fc-event';
            node.style.backgroundColor = event.backgroundColor;
            node.style.borderColor = event.borderColor;
            node.textContent = event.title;
            node.addEventListener('click', () => this.emitEventClick(event));
            return node;
        }

        emitEventClick(event) {
            if (typeof this.options.eventClick === 'function') {
                this.options.eventClick({ event });
            }
        }
    }

    window.FullCalendar = { Calendar };
}());
