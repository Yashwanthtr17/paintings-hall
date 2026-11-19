(function () {
    const gallery = document.getElementById('gallery');
    const form = document.getElementById('add-painting-form');
    const message = document.getElementById('form-message');
    const submitButton = document.getElementById('submit-button');
    let messageTimer;

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    function showMessage(text, type) {
        clearTimeout(messageTimer);
        message.textContent = text;
        message.className = type;
        void message.offsetWidth;
        message.classList.add('show');

        messageTimer = setTimeout(() => {
            message.classList.remove('show');
        }, 4000);
    }

    function updateStats(paintings) {
        const total = paintings.length;
        const artists = new Set(
            paintings.map((painting) => (painting.artist || '').trim()).filter(Boolean)
        ).size;
        const years = paintings
            .map((painting) => Number(painting.year))
            .filter((year) => Number.isFinite(year) && year > 0);
        const range = years.length ? Math.min(...years) + '–' + Math.max(...years) : '—';

        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-artists').textContent = artists;
        document.getElementById('stat-years').textContent = range;
        document.getElementById('gallery-count').textContent =
            total + (total === 1 ? ' piece' : ' pieces');
    }

    function cardTemplate(painting) {
        const title = escapeHtml(painting.title || '');
        const artist = escapeHtml(painting.artist || '');
        const year = painting.year ? escapeHtml(String(painting.year)) : '';
        const description = painting.description
            ? escapeHtml(painting.description).replace(/\n/g, '<br>')
            : '';
        const imageUrl = painting.image_url ? escapeHtml(painting.image_url) : '';

        const media = imageUrl
            ? '<img src="' + imageUrl + '" alt="' + title + '" class="painting-image" loading="lazy">'
            : '<div class="painting-image-placeholder">No image</div>';

        return '<article class="painting-card" data-id="' + escapeHtml(String(painting.id || '')) + '">' +
            '<div class="painting-media">' +
            media +
            (year ? '<span class="painting-year-badge">' + year + '</span>' : '') +
            '</div>' +
            '<div class="painting-body">' +
            '<p class="painting-artist">' + artist + '</p>' +
            '<h3 class="painting-title">' + title + '</h3>' +
            (description ? '<p class="painting-description">' + description + '</p>' : '') +
            '</div>' +
            '</article>';
    }

    function renderGallery(paintings) {
        if (!paintings.length) {
            gallery.innerHTML =
                '<div class="empty-state"><strong>No paintings yet</strong>Add the first painting using the form.</div>';
            return;
        }

        gallery.innerHTML = paintings.map(cardTemplate).join('');
    }

    async function loadPaintings() {
        try {
            const response = await fetch('paintings.php', { cache: 'no-store' });
            if (!response.ok) {
                throw new Error('Failed to load paintings');
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to load paintings');
            }

            const paintings = data.data || [];
            renderGallery(paintings);
            updateStats(paintings);
        } catch (error) {
            gallery.innerHTML =
                '<div class="empty-state empty-state-error"><strong>Unable to load paintings</strong>Please try again later.</div>';
            console.error(error);
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const payload = {
            title: (formData.get('title') || '').trim(),
            artist: (formData.get('artist') || '').trim(),
            year: formData.get('year') || undefined,
            image_url: formData.get('image_url') || undefined,
            description: formData.get('description') || undefined,
        };

        if (!payload.title || !payload.artist) {
            showMessage('Title and artist are required.', 'error');
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Adding...';

        try {
            const response = await fetch('paintings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Failed to add painting');
            }

            showMessage('Painting added to the collection.', 'success');
            form.reset();
            await loadPaintings();
        } catch (error) {
            showMessage(error.message || 'An error occurred.', 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Add painting';
        }
    });

    loadPaintings();
})();
