import React, { useState, useEffect } from "react";
import './App.css';
import RegisterPage from './Pages/RegisterPage';
import LoginPage from './Pages/LoginPage';
import HomePage from './Pages/HomePage';
import axios from 'axios';
import { BrowserRouter as Router, Route, Routes, Navigate } from "react-router-dom"; 

function App() {
  const [registering, setRegistering] = useState(false);
  const [loggedIn, setLoggedIn] = useState(false);
  const [loading, setLoading] = useState(true); 
  const [userInfo, setUserInfo] = useState(null); 

  useEffect(() => {
    // Function to validate the session with the backend on app load
    const validateSession = async () => {
      try {
        const sessionID = localStorage.getItem("sessionID");

        if (sessionID) {
          const response = await axios.post("http://www.sample.com/backend/webserver_backend.php", {
            type: "validateSession",
            sessionId: sessionID
          });
          
          if (response.data.valid) {
            setLoggedIn(true); 
            setUserInfo(response.data.user);
          } else {
            setLoggedIn(false); 
          }
        } else {
          setLoggedIn(false); 
        }
      } catch (error) {
        console.error("Error validating session:", error);
        setLoggedIn(false);
      } finally {
        setLoading(false); 
      }
    };

    validateSession();
  }, []);

  const handleLogout = () => {
    
    localStorage.removeItem("sessionID");

    // Clear userInfo
    setUserInfo(null);
    setLoggedIn(false); 

    // Redirect to login page
    window.location.href = "/"; 
  };

  return (
    <Router>
      <div className="App">
        <h2>InvestZero!</h2>
        <h4>Practice investing for free and learn to grow your wealth!</h4>

        {loading ? (
          <div>Loading...</div> // Show loading state until session validation is complete
        ) : (
          <Routes>
            {/* Login and Registration route */}
            <Route path="/" element={
              loggedIn ? (
                <Navigate to="/home" replace /> // Redirect to home page if logged in
              ) : (
                <>
                  {registering ? <RegisterPage /> : <LoginPage />}
                  <button onClick={() => setRegistering(!registering)}>
                    {registering ? "Already have an account? Login here!" : "Don't have an account? Register here!"}
                  </button>
                </>
              )
            } />

            {/* Home route */}
            <Route path="/home" element={
              loggedIn ? (
                <HomePage user={userInfo} handleLogout={handleLogout} />
              ) : (
                <Navigate to="/" replace /> 
              )
            } />
          </Routes>
        )}
      </div>
    </Router>
  );
}

export default App;
