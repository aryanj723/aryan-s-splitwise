from pymongo import MongoClient
from pymongo.client_session import ClientSession
from urllib.parse import quote_plus

class SessionManager:
    _instance = None
    _client = None
    _session = None

    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(SessionManager, cls).__new__(cls)
            cls._instance._initialize()
        return cls._instance

    def _initialize(self):
        username = quote_plus("aryanj723")
        password = quote_plus("Liketobe@123")
        uri = f"mongodb+srv://{username}:{password}@aryan.udebhrf.mongodb.net/?retryWrites=true&w=majority&appName=Aryan"
        self._client = MongoClient(uri)
        self._session = self._client.start_session()

    @property
    def client(self) -> MongoClient:
        return self._client

    @property
    def session(self) -> ClientSession:
        return self._session

    def end_session(self):
        if self._session:
            self._session.end_session()

session_manager = SessionManager()
