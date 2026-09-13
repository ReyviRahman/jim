document.addEventListener('alpine:init', () => {
    window.Alpine.data('membershipSignature', (property) => ({
        hasInk: false,
        pointerId: null,
        previousPoint: null,

        init() {
            const saved = this.$wire.$get(property);
            if (!saved) return;
            const image = new Image();
            image.onload = () => {
                this.$refs.canvas.getContext('2d').drawImage(image, 0, 0, 800, 480);
                this.hasInk = true;
            };
            image.src = saved;
        },

        point(event) {
            const bounds = this.$refs.canvas.getBoundingClientRect();
            return {
                x: (event.clientX - bounds.left) * 800 / bounds.width,
                y: (event.clientY - bounds.top) * 480 / bounds.height,
            };
        },

        start(event) {
            if (this.pointerId !== null || (event.pointerType === 'mouse' && event.button !== 0)) return;
            this.pointerId = event.pointerId;
            this.$refs.canvas.setPointerCapture(event.pointerId);
            this.previousPoint = this.point(event);
            const context = this.$refs.canvas.getContext('2d');
            context.fillStyle = '#1f2937';
            context.beginPath();
            context.arc(this.previousPoint.x, this.previousPoint.y, 2, 0, Math.PI * 2);
            context.fill();
            this.hasInk = true;
        },

        move(event) {
            if (event.pointerId !== this.pointerId) return;
            const point = this.point(event);
            const context = this.$refs.canvas.getContext('2d');
            context.strokeStyle = '#1f2937';
            context.lineWidth = 4;
            context.lineCap = 'round';
            context.lineJoin = 'round';
            context.beginPath();
            context.moveTo(this.previousPoint.x, this.previousPoint.y);
            context.lineTo(point.x, point.y);
            context.stroke();
            this.previousPoint = point;
        },

        finish(event) {
            if (event.pointerId !== this.pointerId) return;
            this.pointerId = null;
            this.previousPoint = null;
            this.$wire.$set(property, this.$refs.canvas.toDataURL('image/png'), false);
        },

        clear() {
            this.pointerId = null;
            this.previousPoint = null;
            this.$refs.canvas.getContext('2d').clearRect(0, 0, 800, 480);
            this.hasInk = false;
            this.$wire.$set(property, null, false);
        },
    }));
}, { once: true });
