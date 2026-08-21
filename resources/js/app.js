import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('hodInbox', () => ({
    q: '',
    teacher: '',
    classId: '',
    subjectId: '',
    groupBy: 'teacher',
    init() {
        try {
            Object.assign(this, JSON.parse(localStorage.getItem('wisca.hod.inbox') || '{}'));
        } catch (e) {
            // ignore bad local cache
        }

        ['q', 'teacher', 'classId', 'subjectId', 'groupBy'].forEach((key) => {
            this.$watch(key, () => this.persist());
        });
    },
    persist() {
        localStorage.setItem('wisca.hod.inbox', JSON.stringify({
            q: this.q,
            teacher: this.teacher,
            classId: this.classId,
            subjectId: this.subjectId,
            groupBy: this.groupBy,
        }));
    },
    clear() {
        this.q = '';
        this.teacher = '';
        this.classId = '';
        this.subjectId = '';
    },
    match(item) {
        if (this.teacher && String(item.teacherId) !== String(this.teacher)) {
            return false;
        }
        if (this.classId && String(item.classId) !== String(this.classId)) {
            return false;
        }
        if (this.subjectId && String(item.subjectId) !== String(this.subjectId)) {
            return false;
        }
        const query = this.q.trim().toLowerCase();
        if (! query) {
            return true;
        }

        return [item.teacher, item.className, item.subject, item.topic]
            .join(' ')
            .toLowerCase()
            .includes(query);
    },
    anyMatch(items) {
        return items.some((item) => this.match(item));
    },
}));

Alpine.start();
