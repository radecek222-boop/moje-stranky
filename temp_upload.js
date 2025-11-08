  async uploadPhotos(reklamaceId) {
    if (!this.photos || this.photos.length === 0) return;
    try {
      const formData = new FormData();
      formData.append('reklamace_id', reklamaceId);
      formData.append('photo_type', 'problem');
      
      this.photos.forEach((photo, index) => {
        formData.append(`photo_${index}`, photo.data);
        formData.append(`filename_${index}`, `photo_${index + 1}.jpg`);
      });
      
      formData.append('photo_count', this.photos.length);
      
      const response = await fetch('app/controllers/save_photos.php', {
        method: 'POST',
        body: formData
      });
      
      if (!response.ok) {
        throw new Error('Chyba při nahrávání fotek');
      }
      
      const result = await response.json();
      if (result.status !== 'success') {
        throw new Error(result.error || 'Nepodařilo se nahrát fotky');
      }
      console.log('✓ Fotky úspěšně nahrány');
    } catch (error) {
      console.error('Chyba při nahrávání fotek:', error);
    }
  },
