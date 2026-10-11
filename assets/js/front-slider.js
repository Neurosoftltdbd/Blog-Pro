/* Homepage hero slider. Enqueued only on the front page (inc/performance.php). */
document.addEventListener('DOMContentLoaded', function() {
	const slider = document.getElementById('heroSlider');
	if (!slider) return;
	const slides = slider.querySelectorAll('.slide');
	const prevBtn = document.getElementById('prevSlide');
	const nextBtn = document.getElementById('nextSlide');
	let currentSlide = 0;

	if(slides.length === 0) return;

	function showSlide(index) {
		slides.forEach((slide, i) => {
			if (i === index) {
				slide.classList.add('active');
				slide.style.opacity = '1';
				slide.style.zIndex = '2';
			} else {
				slide.classList.remove('active');
				slide.style.opacity = '0';
				slide.style.zIndex = '1';
			}
		});
	}

	function nextSlideFn() {
		currentSlide = (currentSlide + 1) % slides.length;
		showSlide(currentSlide);
	}

	function prevSlideFn() {
		currentSlide = (currentSlide - 1 + slides.length) % slides.length;
		showSlide(currentSlide);
	}

	if(nextBtn) nextBtn.addEventListener('click', nextSlideFn);
	if(prevBtn) prevBtn.addEventListener('click', prevSlideFn);

	// Initialize
	showSlide(0);

	// Auto slide
	let autoTimer = setInterval(nextSlideFn, 5000);
	// Pause the auto-slide while the tab is hidden so it does not queue up.
	document.addEventListener('visibilitychange', function() {
		clearInterval(autoTimer);
		if (!document.hidden) autoTimer = setInterval(nextSlideFn, 5000);
	});
