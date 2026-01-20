/**
 * IndexedDB Helper pro perzistentní ukládání fotek v photocustomer
 *
 * Řeší problém ztráty fotek při:
 * - Restartu telefonu
 * - Odhlášení technika
 * - Timeoutu session
 * - Pádu aplikace
 *
 * Fotky se ukládají lokálně v IndexedDB a obnovují při otevření photocustomer.
 */

const DB_NAME = 'WGS_PhotoStorage';
const DB_VERSION = 1;
const STORE_NAME = 'photoSections';

/**
 * Inicializace IndexedDB
 */
function initPhotoStorageDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onerror = () => {
      console.error('[IndexedDB] Chyba při otevírání databáze:', request.error);
      reject(request.error);
    };

    request.onsuccess = () => {
      console.log('[IndexedDB] Databáze úspěšně otevřena');
      resolve(request.result);
    };

    request.onupgradeneeded = (event) => {
      const db = event.target.result;

      // Vytvořit object store pokud neexistuje
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const objectStore = db.createObjectStore(STORE_NAME, { keyPath: 'reklamaceId' });
        objectStore.createIndex('timestamp', 'timestamp', { unique: false });
        console.log('[IndexedDB] Object store vytvořen');
      }
    };
  });
}

/**
 * Uložit fotky do IndexedDB
 * @param {number} reklamaceId - ID reklamace
 * @param {object} sections - Objekt s fotkami podle sekcí
 */
async function saveSectionsToIndexedDB(reklamaceId, sections) {
  try {
    const db = await initPhotoStorageDB();
    const transaction = db.transaction([STORE_NAME], 'readwrite');
    const objectStore = transaction.objectStore(STORE_NAME);

    const data = {
      reklamaceId: parseInt(reklamaceId),
      sections: sections,
      timestamp: new Date().toISOString(),
      userEmail: JSON.parse(localStorage.getItem('currentUser') || '{}').email || 'unknown'
    };

    const request = objectStore.put(data);

    return new Promise((resolve, reject) => {
      request.onsuccess = () => {
        console.log(`[IndexedDB] Fotky uloženy pro reklamaci #${reklamaceId}`);
        console.log(`[IndexedDB] Celkem sekcí: ${Object.keys(sections).length}`);

        // Spočítat celkový počet fotek
        let totalPhotos = 0;
        Object.keys(sections).forEach(key => {
          totalPhotos += sections[key].length;
        });
        console.log(`[IndexedDB] Celkem fotek: ${totalPhotos}`);

        resolve(true);
      };

      request.onerror = () => {
        console.error('[IndexedDB] Chyba při ukládání:', request.error);
        reject(request.error);
      };

      transaction.oncomplete = () => {
        db.close();
      };
    });
  } catch (error) {
    console.error('[IndexedDB] Chyba při ukládání do IndexedDB:', error);
    return false;
  }
}

/**
 * Načíst fotky z IndexedDB
 * @param {number} reklamaceId - ID reklamace
 * @returns {object|null} - Objekt s fotkami nebo null
 */
async function loadSectionsFromIndexedDB(reklamaceId) {
  try {
    const db = await initPhotoStorageDB();
    const transaction = db.transaction([STORE_NAME], 'readonly');
    const objectStore = transaction.objectStore(STORE_NAME);

    const request = objectStore.get(parseInt(reklamaceId));

    return new Promise((resolve, reject) => {
      request.onsuccess = () => {
        const data = request.result;

        if (data && data.sections) {
          console.log(`[IndexedDB] Fotky načteny pro reklamaci #${reklamaceId}`);
          console.log(`[IndexedDB] Timestamp: ${data.timestamp}`);

          // Spočítat celkový počet fotek
          let totalPhotos = 0;
          Object.keys(data.sections).forEach(key => {
            totalPhotos += data.sections[key].length;
          });
          console.log(`[IndexedDB] Obnoveno ${totalPhotos} fotek`);

          resolve(data.sections);
        } else {
          console.log(`[IndexedDB] Žádné uložené fotky pro reklamaci #${reklamaceId}`);
          resolve(null);
        }
      };

      request.onerror = () => {
        console.error('[IndexedDB] Chyba při načítání:', request.error);
        reject(request.error);
      };

      transaction.oncomplete = () => {
        db.close();
      };
    });
  } catch (error) {
    console.error('[IndexedDB] Chyba při načítání z IndexedDB:', error);
    return null;
  }
}

/**
 * Smazat fotky z IndexedDB po úspěšném odeslání
 * @param {number} reklamaceId - ID reklamace
 */
async function deleteSectionsFromIndexedDB(reklamaceId) {
  try {
    const db = await initPhotoStorageDB();
    const transaction = db.transaction([STORE_NAME], 'readwrite');
    const objectStore = transaction.objectStore(STORE_NAME);

    const request = objectStore.delete(parseInt(reklamaceId));

    return new Promise((resolve, reject) => {
      request.onsuccess = () => {
        console.log(`[IndexedDB] Fotky smazány pro reklamaci #${reklamaceId}`);
        resolve(true);
      };

      request.onerror = () => {
        console.error('[IndexedDB] Chyba při mazání:', request.error);
        reject(request.error);
      };

      transaction.oncomplete = () => {
        db.close();
      };
    });
  } catch (error) {
    console.error('[IndexedDB] Chyba při mazání z IndexedDB:', error);
    return false;
  }
}

/**
 * Vyčistit staré záznamy (starší než 7 dní)
 */
async function cleanOldPhotos() {
  try {
    const db = await initPhotoStorageDB();
    const transaction = db.transaction([STORE_NAME], 'readwrite');
    const objectStore = transaction.objectStore(STORE_NAME);
    const index = objectStore.index('timestamp');

    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);

    const range = IDBKeyRange.upperBound(sevenDaysAgo.toISOString());
    const request = index.openCursor(range);

    let deletedCount = 0;

    request.onsuccess = (event) => {
      const cursor = event.target.result;
      if (cursor) {
        cursor.delete();
        deletedCount++;
        cursor.continue();
      } else {
        if (deletedCount > 0) {
          console.log(`[IndexedDB] Vyčištěno ${deletedCount} starých záznamů`);
        }
      }
    };

    transaction.oncomplete = () => {
      db.close();
    };
  } catch (error) {
    console.error('[IndexedDB] Chyba při čištění starých fotek:', error);
  }
}

/**
 * Získat seznam všech uložených reklamací
 */
async function getAllStoredRecords() {
  try {
    const db = await initPhotoStorageDB();
    const transaction = db.transaction([STORE_NAME], 'readonly');
    const objectStore = transaction.objectStore(STORE_NAME);

    const request = objectStore.getAll();

    return new Promise((resolve, reject) => {
      request.onsuccess = () => {
        const records = request.result;
        console.log(`[IndexedDB] Celkem uložených reklamací: ${records.length}`);
        resolve(records);
      };

      request.onerror = () => {
        console.error('[IndexedDB] Chyba při načítání seznamu:', request.error);
        reject(request.error);
      };

      transaction.oncomplete = () => {
        db.close();
      };
    });
  } catch (error) {
    console.error('[IndexedDB] Chyba při získávání seznamu:', error);
    return [];
  }
}

// Export funkcí pro použití v photocustomer.js
if (typeof window !== 'undefined') {
  window.PhotoStorageDB = {
    save: saveSectionsToIndexedDB,
    load: loadSectionsFromIndexedDB,
    delete: deleteSectionsFromIndexedDB,
    cleanOld: cleanOldPhotos,
    getAll: getAllStoredRecords
  };
}
