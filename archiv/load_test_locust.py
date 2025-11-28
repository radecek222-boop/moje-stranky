#!/usr/bin/env python3
"""
WGS Service - Locust Load Test Script
======================================

Komplexní load test pro White Glove Service systém.

Instalace:
    pip install locust

Spuštění:
    locust -f load_test_locust.py --host=https://www.wgs-service.cz

Web UI:
    http://localhost:8089

Scénáře:
    1. Login (admin + regular users)
    2. Seznam reklamací (GET /api/statistiky_api.php)
    3. Detail reklamace (GET /api/notes_api.php)
    4. Přidání poznámky (POST /api/notes_api.php)
    5. Vytvoření reklamace (POST /app/controllers/save.php)

Expected Results:
    - 50 users: 95% success, <2.5s response time
    - 80 users: 75% success, <8s response time (začíná brzdit)
    - 100 users: 45% success, >8s response time (kolaps)
"""

import random
import time
import json
from locust import HttpUser, task, between, events
from locust.exception import RescheduleTask


class WGSUser(HttpUser):
    """
    Simuluje běžného uživatele WGS systému
    """
    wait_time = between(1, 3)  # Čekání mezi requesty

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.csrf_token = None
        self.session_cookies = None
        self.user_email = None
        self.user_role = None

    def on_start(self):
        """
        Spustí se při startu každého virtuálního uživatele
        """
        self.login()

    def login(self):
        """
        Přihlášení uživatele (admin nebo běžný user)
        """
        # 80% chance běžný user, 20% admin
        is_admin = random.random() < 0.2

        if is_admin:
            credentials = {
                "email": "admin@wgs-service.cz",
                "password": "admin_password_here",  # ⚠️ ZMĚNIT!
                "admin_key": "your_admin_key_here"  # ⚠️ ZMĚNIT!
            }
            self.user_role = "admin"
        else:
            # Rotace mezi 3 test uživateli
            test_users = [
                {"email": "test1@wgs.cz", "password": "TestPassword123!"},
                {"email": "test2@wgs.cz", "password": "TestPassword123!"},
                {"email": "test3@wgs.cz", "password": "TestPassword123!"},
            ]
            credentials = random.choice(test_users)
            self.user_role = "user"

        self.user_email = credentials["email"]

        with self.client.post(
            "/app/controllers/login_controller.php",
            data=credentials,
            catch_response=True,
            name="01_login"
        ) as response:
            if response.status_code == 200:
                try:
                    data = response.json()
                    if data.get("status") == "success":
                        self.csrf_token = data.get("csrf_token")
                        response.success()
                    else:
                        response.failure(f"Login failed: {data.get('message')}")
                except json.JSONDecodeError:
                    response.failure("Invalid JSON response")
            else:
                response.failure(f"HTTP {response.status_code}")

    @task(10)
    def get_seznam_reklamaci(self):
        """
        Načtení seznamu reklamací (NEJVÍCE FREKVENTOVANÝ)
        Toto je hot path endpoint - každý user ho volá často
        """
        if not self.csrf_token:
            raise RescheduleTask()

        with self.client.get(
            "/api/statistiky_api.php?action=list",
            cookies=self.session_cookies,
            catch_response=True,
            name="02_seznam_reklamaci"
        ) as response:
            if response.status_code == 200:
                try:
                    data = response.json()
                    if data.get("status") == "success":
                        response.success()
                    else:
                        response.failure(f"API error: {data.get('message')}")
                except json.JSONDecodeError:
                    response.failure("Invalid JSON")
            else:
                response.failure(f"HTTP {response.status_code}")

    @task(8)
    def get_notes_for_reklamace(self):
        """
        Načtení poznámek k reklamaci
        """
        if not self.csrf_token:
            raise RescheduleTask()

        # Random reklamace ID (1-100)
        reklamace_id = f"WGS/2025/{random.randint(1, 100):05d}"

        with self.client.get(
            f"/api/notes_api.php?action=get&reklamace_id={reklamace_id}",
            cookies=self.session_cookies,
            catch_response=True,
            name="03_get_notes"
        ) as response:
            if response.status_code == 200 or response.status_code == 404:
                # 404 je OK (reklamace neexistuje)
                response.success()
            else:
                response.failure(f"HTTP {response.status_code}")

    @task(5)
    def add_note(self):
        """
        Přidání poznámky (POST operace)
        Testuje session locking + transaction handling
        """
        if not self.csrf_token:
            raise RescheduleTask()

        reklamace_id = f"WGS/2025/{random.randint(1, 100):05d}"

        payload = {
            "action": "add",
            "reklamace_id": reklamace_id,
            "note_text": f"Test poznámka {time.time()}",
            "csrf_token": self.csrf_token
        }

        with self.client.post(
            "/api/notes_api.php",
            data=payload,
            cookies=self.session_cookies,
            catch_response=True,
            name="04_add_note"
        ) as response:
            if response.status_code == 200:
                try:
                    data = response.json()
                    if data.get("status") == "success":
                        response.success()
                    else:
                        response.failure(f"API error: {data.get('message')}")
                except json.JSONDecodeError:
                    response.failure("Invalid JSON")
            else:
                response.failure(f"HTTP {response.status_code}")

    @task(3)
    def get_user_stats(self):
        """
        Načtení statistik uživatele (welcome modal)
        """
        if not self.csrf_token:
            raise RescheduleTask()

        with self.client.get(
            "/api/get_user_stats.php",
            cookies=self.session_cookies,
            catch_response=True,
            name="05_user_stats"
        ) as response:
            if response.status_code == 200:
                response.success()
            else:
                response.failure(f"HTTP {response.status_code}")

    @task(2)
    def get_pricing(self):
        """
        Načtení ceníku (méně frekventované)
        """
        with self.client.get(
            "/api/pricing_api.php?action=list",
            catch_response=True,
            name="06_get_pricing"
        ) as response:
            if response.status_code == 200:
                response.success()
            else:
                response.failure(f"HTTP {response.status_code}")

    @task(1)
    def create_reklamace(self):
        """
        Vytvoření nové reklamace (nejkomplexnější operace)
        Testuje: session locking + transakce + SELECT * + file I/O
        """
        if not self.csrf_token:
            raise RescheduleTask()

        payload = {
            "action": "add",
            "typ": "reklamace",
            "jmeno": f"Test User {random.randint(1000, 9999)}",
            "telefon": f"+420{random.randint(100000000, 999999999)}",
            "email": f"test{random.randint(1, 1000)}@wgs.cz",
            "adresa": "Test Address 123, Praha",
            "popis_problemu": "Testovací popis problému pro load test",
            "model": "Test Model XYZ",
            "csrf_token": self.csrf_token
        }

        with self.client.post(
            "/app/controllers/save.php",
            data=payload,
            cookies=self.session_cookies,
            catch_response=True,
            name="07_create_reklamace"
        ) as response:
            if response.status_code == 200:
                try:
                    data = response.json()
                    if data.get("status") == "success":
                        response.success()
                    else:
                        response.failure(f"Create failed: {data.get('message')}")
                except json.JSONDecodeError:
                    response.failure("Invalid JSON")
            else:
                response.failure(f"HTTP {response.status_code}")


class WGSAdminUser(HttpUser):
    """
    Simuluje admin uživatele (heavy operations)
    """
    wait_time = between(2, 5)
    weight = 1  # Méně admin userů (1:5 ratio s běžnými)

    def on_start(self):
        """Admin login"""
        credentials = {
            "email": "admin@wgs-service.cz",
            "admin_key": "your_admin_key_here",  # ⚠️ ZMĚNIT!
        }

        response = self.client.post(
            "/app/controllers/login_controller.php",
            data=credentials
        )

        if response.status_code == 200:
            data = response.json()
            self.csrf_token = data.get("csrf_token")

    @task(5)
    def get_all_statistics(self):
        """
        Načtení všech statistik (HEAVY query)
        """
        with self.client.get(
            "/api/statistiky_api.php?action=summary",
            catch_response=True,
            name="ADMIN_01_statistics"
        ) as response:
            if response.status_code == 200:
                response.success()
            else:
                response.failure(f"HTTP {response.status_code}")

    @task(2)
    def generate_protocol(self):
        """
        Generování PDF protokolu (NEJDELŠÍ operace - 1-3s)
        Testuje session locking u long-running operations
        """
        reklamace_id = f"WGS/2025/{random.randint(1, 50):05d}"

        with self.client.post(
            "/api/protokol_api.php",
            data={
                "action": "generate",
                "reklamace_id": reklamace_id,
                "csrf_token": self.csrf_token
            },
            catch_response=True,
            name="ADMIN_02_generate_pdf",
            timeout=30  # PDF může trvat dlouho
        ) as response:
            if response.status_code == 200:
                response.success()
            else:
                response.failure(f"HTTP {response.status_code}")


# ==========================================
# EVENT LISTENERS PRO MONITORING
# ==========================================

@events.test_start.add_listener
def on_test_start(environment, **kwargs):
    print("\n" + "="*60)
    print("🚀 WGS LOAD TEST STARTED")
    print("="*60)
    print(f"Host: {environment.host}")
    print(f"Users: {environment.runner.target_user_count if hasattr(environment.runner, 'target_user_count') else 'N/A'}")
    print("="*60 + "\n")


@events.test_stop.add_listener
def on_test_stop(environment, **kwargs):
    print("\n" + "="*60)
    print("🏁 WGS LOAD TEST COMPLETED")
    print("="*60)

    stats = environment.stats.total

    print(f"\n📊 RESULTS:")
    print(f"   Total requests: {stats.num_requests}")
    print(f"   Failures: {stats.num_failures} ({stats.fail_ratio*100:.2f}%)")
    print(f"   Median response time: {stats.median_response_time} ms")
    print(f"   95th percentile: {stats.get_response_time_percentile(0.95)} ms")
    print(f"   Requests/s: {stats.total_rps:.2f}")

    if stats.fail_ratio > 0.2:
        print("\n⚠️  WARNING: >20% failure rate!")
        print("   System is under stress - consider:")
        print("   1. Reducing concurrent users")
        print("   2. Implementing Redis sessions")
        print("   3. Adding session_write_close()")

    if stats.median_response_time > 2000:
        print("\n⚠️  WARNING: Median response time >2s!")
        print("   Performance degradation detected")

    print("="*60 + "\n")


# ==========================================
# USAGE INSTRUCTIONS
# ==========================================
"""
ZÁKLADNÍ SPUŠTĚNÍ:
    locust -f load_test_locust.py --host=https://www.wgs-service.cz

HEADLESS MODE (bez Web UI):
    locust -f load_test_locust.py --host=https://www.wgs-service.cz \\
           --users 50 --spawn-rate 5 --run-time 5m --headless

TESTOVACÍ SCÉNÁŘE:

1. LIGHT LOAD (baseline):
   --users 20 --spawn-rate 2 --run-time 3m

2. MEDIUM LOAD (typical usage):
   --users 50 --spawn-rate 5 --run-time 5m

3. HEAVY LOAD (stress test):
   --users 100 --spawn-rate 10 --run-time 10m

4. BREAKING POINT TEST:
   --users 150 --spawn-rate 5 --run-time 15m

VÝSTUP DO SOUBORU:
    locust -f load_test_locust.py \\
           --host=https://www.wgs-service.cz \\
           --users 100 --spawn-rate 10 --run-time 10m \\
           --headless --html report.html --csv results

EXPECTED BREAKING POINT: ~85 concurrent users

⚠️  DŮLEŽITÉ:
   - Před testem změňte admin credentials!
   - Vytvořte test uživatele (test1@wgs.cz, test2@wgs.cz, test3@wgs.cz)
   - Spouštějte POUZE na test/staging serveru!
   - NIKDY nespouštějte na produkci bez upozornění!
"""
