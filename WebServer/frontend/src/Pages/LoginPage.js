import React, { useState } from "react";
import axios from "axios";
import getBackendURL from "../Utils/backendURL";

function LoginPage() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [code, setCode] = useState("");
  const [userId, setUserId] = useState("");
  const [isCodeSent, setIsCodeSent] = useState(false);
  const [error, setError] = useState("");

  const handleSubmission = async (event) => {
    event.preventDefault();

    if (!isCodeSent) {
      try {
        const response = await axios.post(
          getBackendURL(),
          { type: "LOGIN", username, password },
          { withCredentials: true }
        );

        if (response.data.success) {
          window.alert("Login code sent to your email.");
          setIsCodeSent(true); // Move to code verification step
          setUserId(response.data.user.id); // Save user ID for 2FA verification
        } else {
          setError("Login failed. Please try again.");
        }
      } catch (error) {
        console.error("Error during login:", error);
        setError("Error during login. Please try again.");
        setPassword("");
      }
    } else { // Handle code verification
      try {
        const response = await axios.post(
          getBackendURL(),
          { type: "VERIFY2FA", code, userId },
          { withCredentials: true }
        );

        if (response.data.success) {
          window.alert("Login successful!");
          window.location.href = "/home";
        } else {
          setError("Invalid code. Please try again.");
          setCode(""); // Reset code input
        }
      } catch (error) {
        console.error("Error during 2FA verification:", error);
        setError("Error during 2FA verification. Please try again.");
        setCode("");
      }
    }
  };

  return (
    <div>
      <h1>Welcome to InvestZero!</h1>
      <h2>Please Login</h2>
      {error && <div><h3>{error}</h3></div>}
      <form onSubmit={handleSubmission}>
        {!isCodeSent ? (
          <>
            <div>
              <input
                type="text"
                value={username}
                placeholder="Username"
                onChange={(e) => setUsername(e.target.value)}
                required
              />
            </div>
            <div>
              <input
                type="password"
                value={password}
                placeholder="Password"
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>
            <button type="submit">Login</button>
          </>
        ) : (
          <>
            <div>
              <input
                type="text"
                value={code}
                placeholder="Enter your code"
                onChange={(e) => setCode(e.target.value)}
                required
              />
            </div>
            <button type="submit">Verify Code</button>
          </>
        )}
      </form>
    </div>
  );
}

export default LoginPage;
