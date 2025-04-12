import React, { useState, useEffect } from "react";
import './App.css';
import RegisterPage from './Pages/RegisterPage';
import LoginPage from './Pages/LoginPage';
import HomePage from './Pages/HomePage';
import SearchAllStocks from './Pages/SearchAllStocks';
import ChartPage from "./Components/ChartPage";
import axios from 'axios';
import { BrowserRouter as Router, Route, Routes, Navigate } from "react-router-dom";
import getBackendURL from "./Utils/backendURL";

function App() {
  const [registering, setRegistering] = useState(false);
  const [loggedIn, setLoggedIn] = useState(false);
  const [loading, setLoading] = useState(true);
  const [userInfo, setUserInfo] = useState(null);

  useEffect(() => {
    const validateSession = async () => {
      try {
        const response = await axios.post(
          getBackendURL(),
          { type: "VALIDATE_SESSION" },
          { withCredentials: true }
        );

        if (response.status === 200 && response.data && response.data.valid) {
          setLoggedIn(true);
          setUserInfo(response.data.user);
        } else {
          setLoggedIn(false);
        }
      } catch (error) {
        console.error("Session validation error:", error);
      } finally {
        setLoading(false);
      }
    };

    validateSession();
  }, []);

  const handleLogout = async () => {
    try {
      await axios.post(
        getBackendURL(),
        { type: "LOGOUT" },
        { withCredentials: true }
      );

      setUserInfo(null);
      setLoggedIn(false);

      window.alert("You have been logged out.");
      window.location.href = "/";
    } catch (error) {
      console.error("Logout error:", error);
    }
  };

  return (
    <Router basename="/">
      <div className="App text-center w-100 d-flex flex-column justify-content-center align-items-center">
      {loading ? (
          <div className="d-flex justify-content-center align-items-center" style={{ height: '100vh' }}>
            <div>Loading...</div>
          </div>
        ) : (
          <Routes>
            <Route path="/" element={
              loggedIn ? (
                <Navigate to="/home" replace />
              ) : (
                <div className="d-flex justify-content-center align-items-center" >
                  <div className="text-center w-100">
                    {registering ? <RegisterPage /> : <LoginPage />}
                    <button
                      className="btn btn-primary mt-4"
                      onClick={() => setRegistering(!registering)}
                    >
                      {registering
                        ? "Already have an account? Login here!"
                        : "Don't have an account? Register here!"}
                    </button>
                  </div>
                </div>
              )
            } />

            <Route path="/home" element={
              loggedIn ? (
                <HomePage user={userInfo} handleLogout={handleLogout} />
              ) : (
                <Navigate to="/" replace />
              )
            } />

            <Route path="/searchallstocks" element={
              loggedIn ? (
                <SearchAllStocks user={userInfo} handleLogout={handleLogout} />
              ) : (
                <Navigate to="/" />
              )
            } />

            <Route path="/chartpage/:Ticker" element={
              loggedIn ? <ChartPage /> : <Navigate to="/" />
            } />

            <Route path="*" element={<Navigate to="/" />} />
          </Routes>
        )}
      </div>
    </Router>
  );
}

export default App;
