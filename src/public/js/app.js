(function () {
    const gallery = document.getElementById('gallery');
    const form = document.getElementById('add-painting-form');
    const message = document.getElementById('form-message');

    async function loadPaintings() {
        try {
            const res = await fetch('paintings.php', { cache: 'no-store' });
            if (!res.ok) throw new Error('Failed to load paintings');
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Failed to load paintings');
            renderGallery(data.data || []);
        } catch (err) {
            gallery.innerHTML = '<p style="color:var(--danger,#dc2626)">Unable to load paintings. Please try again later.</p>';
            console.error(err);
        }
    }

    function renderGallery(paintings) {
        if (!paintings.length) {
            gallery.innerHTML = '<p>No paintings yet. Add one below!</p>';
            return;
        }
        gallery.innerHTML = paintings.map(p => {
            const title = escapeHtml(p.title ?? '');
            const artist = escapeHtml(p.artist ?? '');
            const year = p.year ? escapeHtml(String(p.year)) : '';
            const description = p.description ? escapeHtml(p.description).replace(/\n/g, '<br>') : '';
            const imageUrl = p.image_url ? escapeHtml(p.image_url) : '';
            const imageTag = imageUrl
                ? '<img src="' + imageUrl + '" alt="' + title + '" class="painting-image">'
                : '<div class="painting-image-placeholder">No image</div>';
            return '<article class="painting-card" data-id="' + escapeHtml(String(p.id ?? '')) + '">' +
                imageTag +
                '<div class="painting-info">' +
                '<h3 class="painting-title">' + title + '</h3>' +
                '<p class="painting-artist">' + artist + '</p>' +
                (year ? '<p class="painting-year">' + year + '</p>' : '') +
                (description ? '<p class="painting-description">' + description + '</p>' : '') +
                '</div>' +
                '</article>';
        }).join('');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function showMessage(text, type) {
        message.textContent = text;
        message.className = 'success'; // default
        if (type === 'error') message.className = 'error';
        if (type === 'success') message.className = 'success';
        message.style.display = 'block';
        setTimeout(() => { message.style.display = 'none'; }, 5000);
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const payload = {
            title: formData.get('title'),
            artist: formData.get('artist'),
            year: formData.get('year') || undefined,
            image_url: formData.get('image_url') || undefined,
            description: formData.get('description') || undefined,
        };

        try {
            const res = await fetch('paintings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || 'Failed to add painting');
            showMessage('Painting added!', 'success');
            form.reset();
            loadPaintings();
        } catch (err) {
            showMessage(err.message || 'An error occurred.', 'error');
        }
    });

    loadPaintings();
})();
