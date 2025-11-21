class SuspendedParticles {
    constructor() {
        this.container = document.getElementById('particle-field');
        this.particles = [];
        this.mouseX = 0;
        this.mouseY = 0;
        this.mouseActive = false;

        this.settings = {
            particleCount: 80,
            attractionRadius: 120,
            repulsionRadius: 40,
            attractionStrength: 0.8,
            repulsionStrength: 1.2,
            floatStrength: 0.3,
            noiseSpeed: 0.0002,
            returnSpeed: 0.02
        };

        this.init();
    }

    init() {
        this.createParticles();

        document.addEventListener('mousemove', (e) => {
            this.mouseX = e.clientX;
            this.mouseY = e.clientY;
            this.mouseActive = true;
        });

        document.addEventListener('mouseleave', () => {
            this.mouseActive = false;
        });

        this.animate();
    }

    createParticles() {
        for (let i = 0; i < this.settings.particleCount; i++) {
            const particle = document.createElement('div');

            // Distribución uniforme en la pantalla
            const x = Math.random() * window.innerWidth;
            const y = Math.random() * window.innerHeight;

            const sizes = ['tiny', 'small', 'medium', 'large'];
            const size = sizes[Math.floor(Math.random() * sizes.length)];

            particle.className = `particle particle-${size}`;
            particle.style.cssText = `
        left: ${x}px;
        top: ${y}px;
        opacity: ${0.3 + Math.random() * 0.4};
      `;

            this.container.appendChild(particle);

            this.particles.push({
                element: particle,
                x: x, y: y,
                baseX: x, baseY: y,
                velocity: { x: 0, y: 0 },
                noiseOffset: Math.random() * 1000,
                mass: 0.8 + Math.random() * 1.2,
                size: size
            });
        }
    }

    animate() {
        const time = Date.now() * this.settings.noiseSpeed;

        this.particles.forEach(particle => {
            // Movimiento de flotación natural (muy suave)
            const floatX = Math.sin(time + particle.noiseOffset) * this.settings.floatStrength;
            const floatY = Math.cos(time + particle.noiseOffset * 1.3) * this.settings.floatStrength;

            let targetX = particle.baseX + floatX;
            let targetY = particle.baseY + floatY;

            // Interacción con el cursor
            if (this.mouseActive) {
                const dx = particle.x - this.mouseX;
                const dy = particle.y - this.mouseY;
                const distance = Math.sqrt(dx * dx + dy * dy);

                if (distance < this.settings.attractionRadius) {
                    const angle = Math.atan2(dy, dx);

                    if (distance < this.settings.repulsionRadius) {
                        // Repulsión cercana
                        const force = (1 - distance / this.settings.repulsionRadius) * this.settings.repulsionStrength;
                        targetX = particle.x + Math.cos(angle) * force * 20;
                        targetY = particle.y + Math.sin(angle) * force * 20;
                    } else {
                        // Atracción suave
                        const force = (1 - distance / this.settings.attractionRadius) * this.settings.attractionStrength;
                        targetX = particle.x - Math.cos(angle) * force * 10;
                        targetY = particle.y - Math.sin(angle) * force * 10;
                    }
                }
            }

            // Suavizar movimiento hacia la posición target
            particle.x += (targetX - particle.x) * 0.05;
            particle.y += (targetY - particle.y) * 0.05;

            // Retorno muy gradual a posición base cuando no hay interacción
            if (!this.mouseActive) {
                particle.x += (particle.baseX - particle.x) * this.settings.returnSpeed;
                particle.y += (particle.baseY - particle.y) * this.settings.returnSpeed;
            }

            // Aplicar transformación suave
            const translateX = (particle.x - parseFloat(particle.element.style.left)) || 0;
            const translateY = (particle.y - parseFloat(particle.element.style.top)) || 0;

            particle.element.style.transform = `translate(${translateX}px, ${translateY}px)`;

            // Efecto de opacidad sutil basado en movimiento
            const movement = Math.abs(translateX) + Math.abs(translateY);
            particle.element.style.opacity = Math.min(0.7, 0.3 + movement * 2);
        });

        requestAnimationFrame(() => this.animate());
    }
}

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    new SuspendedParticles();
});