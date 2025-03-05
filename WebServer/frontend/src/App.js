import React, { useState, useEffect } from "react";
import './App.css';
import RegisterPage from './Pages/RegisterPage';
import LoginPage from './Pages/LoginPage';
import HomePage from './Pages/HomePage';
import TransactionsPage from './Pages/TransactionsPage';
import axios from 'axios';
import { BrowserRouter as Router, Route, Routes, Navigate } from "react-router-dom"; 

function App() {
  const [registering, setRegistering] = useState(false);
  const [loggedIn, setLoggedIn] = useState(true); // change to false
  const [loading, setLoading] = useState(false);  // change to true
  const [userInfo, setUserInfo] = useState(null);

  useEffect(() => {
   
  
    const validateSession = async () => {
      try {
        const response = await axios.post(
          "http://www.sample.com/backend/webserver_backend.php",
          { type: "validateSession" },
          { withCredentials: true }
        );
  
        
          console.log("Session Validation Response:", response.data);
          if (response.data.valid) {
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

    setUserInfo({"username":"tds22"}) // remove this
  
    //validateSession(); un comment
    
    
  }, []);
  

  const handleLogout = async () => {
    try {
      await axios.post(
        "http://www.sample.com/backend/webserver_backend.php",
        { type: "logout" },
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
      <div className="App">
        <h2>InvestZero!</h2>
        <h4>Practice investing for free and learn to grow your wealth!</h4>

        {loading ? (
          <div>Loading...</div> 
        ) : (
          <Routes>
            <Route path="/" element={
              loggedIn ? (
                <Navigate to="/home" replace />
              ) : (
                <>
                  {registering ? <RegisterPage /> : <LoginPage />}
                  <button onClick={() => setRegistering(!registering)}>
                    {registering ? "Already have an account? Login here!" : "Don't have an account? Register here!"}
                  </button>
                </>
              )
            } />

            <Route path="/home" element={
              loggedIn ? (
                <HomePage user={userInfo} handleLogout={handleLogout} />
              ) : (
                <Navigate to="/" replace />
              )
            } />
              <Route path="/transactions" element={
                true ? <TransactionsPage user={userInfo} handleLogout={handleLogout} /> : <Navigate to="/" replace /> //CHANGE TO loggedIN instead of true
              } />

          </Routes>
        )}
      </div>
    </Router>
  );
}

export default App;
