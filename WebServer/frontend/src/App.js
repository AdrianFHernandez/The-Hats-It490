import React from "react"
import './App.css';
<<<<<<< HEAD
import RegisterPage from './Pages/RegisterPage';
import LoginPage from './Pages/LoginPage';
import { useState } from "react";
import HomePage from "./Pages/HomePage";

function App() {
  const [isRegistered, setIsRegistered] = useState(false);
  const [loggedIn, setLoggedIn] = useState(false);
  const [isRegistering, setIsRegistering] = useState(false);

  const handleLogin = () => {
    setLoggedIn(true);
  }

  const handleRegistration = () => {
    setIsRegistered(true);
    setRegistering(false);
  }
  

  return (
    <div className="App">
      <p>hello there</p>

      {loggedIn ?(

      <HomePage user={{name:"Tsewang", email:"email"}}></HomePage>)
      : registering ? (
        <RegisterPage onRegister={handleRegistration} />
      ) : (
        <LoginPage onLogin = {handleLogin} />
      )
    }

      
      <h2>InvestZero!</h2>
      <h4>Practice investing for free and learn to grow your wealth!</h4>
      {registering ? <RegisterPage /> : <LoginPage />}
      <button onClick={() => setRegistering(!registering)}>
        {registering ? "Already have an account? Login here!" : "Don't have an account? Register here!"}    
      </button> 
    </div>
  );
}

export default App;
