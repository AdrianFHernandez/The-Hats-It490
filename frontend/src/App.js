import React from "react"
import './App.css';
import RegisterPage from './Pages/RegisterPage';
import LoginPage from './Pages/LoginPage';
import { useState } from "react";

function App() {
  const [registering, setRegistering] = useState(false)
  

  return (
    <div className="App">
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
