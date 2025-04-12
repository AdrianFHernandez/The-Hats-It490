import React, { useState } from "react";
import axios from "axios";
import getBackendURL from "../Utils/backendURL";

function LoginPage() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");

  const handleSubmission = async (event) => {
    event.preventDefault();

    try {
      const response = await axios.post(
        getBackendURL(),
        { type: "LOGIN", username, password },
        { withCredentials: true } // Send cookies
      );

      console.log(JSON.stringify(response.data));
      window.alert("Login Response:", JSON.stringify(response.data));

      if (response.data.success) {
        window.alert("Login successful!");
        window.location.href = "/home";
      } else {
        setError("Login failed. Please try again.");
      }
    } catch (error) {
      console.error("Error during login:", error);
      setError("Error during login. Please try again.");
      setPassword("");
    }
  };

  return (
    <div>
      <div className="mt-5">
        <h1
          className="mb-3 fw-bold text-center text-truncate"
          style={{ maxWidth: "100%", whiteSpace: "nowrap", overflow: "hidden" }}
        >
          Welcome to InvestZero!
        </h1>
        <h4
          className="mb-4 w-100 text-center fs-6 text-light"
          style={{ maxWidth: "80%", margin: "0 auto" }}
        >
          Practice investing for free and learn to grow your wealth!
        </h4>

        <h2 className="my-4 ">Please LOGIN</h2>
      </div>
      {error && (
        <div>
          <h3>{error}</h3>
        </div>
      )}
      <form onSubmit={handleSubmission}>
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
        <button type="submit" className="btn btn-success mt-2 px-5">
          LOGIN
        </button>
      </form>
    </div>
  );
}

export default LoginPage;
