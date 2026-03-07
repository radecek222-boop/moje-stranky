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
      resolve(request.result);
    };

    request.onupgradeneeded = (event) => {
      const db = event.target.result;

      // Vytvořit object store pokud neexistuje
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const objectStore = db.createObjectStore(STORE_NAME, { keyPath: 'reklamaceId' });
        objectStore.createIndex('timestamp', 'timestamp', { unique: false });
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
      reklamaceId: isNaN(parseInt(reklamaceId)) ? reklamaceId : parseInt(reklamaceId),
      sections: sections,
      timestamp: new Date().toISOString(),
      userEmail: JSON.parse(localStorage.getItem('currentUser') || '{}').email || 'unknown'
    };

    const request = objectStore.put(data);

    return new Promise((resolve, reject) => {
      request.onsuccess = () => {
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

    const parsovaneId = parseInt(reklamaceId);
    const request = objectStore.get(isNaN(parsovaneId) ? reklamaceId : parsovaneId);

    return new Promise((resolve, reject) => {
      request.onsuccess = () => {
        const data = request.result;

        if (data && data.sections) {
          resolve(data.sections);
        } else {
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

    const parsovaneId = parseInt(reklamaceId);
    const request = objectStore.delete(isNaN(parsovaneId) ? reklamaceId : parsovaneId);

    return new Promise((resolve, reject) => {
      request.onsuccess = () => {
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
