import React, { useState } from "react";
import axios from "axios";
import getBackendURL from "../Utils/backendURL";

function RegisterPage(props) {
  const [name, setName] = useState("");
  const [username, setUsername] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [visible, setVisible] = useState(false);

  //const [showPassword, setshowPassword] = useState(false);

  async function handleSubmission(event) {
    event.preventDefault(); // Prevent form submission

    // Step 1: Validate Passwords Match
    if (password !== confirmPassword) {
      setError("Passwords do not match. Please recheck.");
      return;
    } else {
      setError(""); // Clear error if passwords match
    }

    const hasLowerCase = /[a-z]/.test(password);
    const hasUpperCase = /[A-Z]/.test(password);
    const hasDigit = /\d/.test(password);
    const hasSpecialChar = /[!@#$%&*]/.test(password);
    const minLength = password.length >= 8;

    if (!hasUpperCase) {
      setError("Password must contain at least one uppercase letter");
      return;
    } else {
      setError(""); // Clear error if password is valid
    }

    if (!hasLowerCase) {
      setError("Password must contain at least one lowercase letter");
      return;
    } else {
      setError(""); // Clear error if password is valid
    }

    if (!hasSpecialChar) {
      setError("Password must contain at least one special character");
      return;
    } else {
      setError(""); // Clear error if password is valid
    }

    if (!hasDigit) {
      setError("Password must contain at least one number");
      return;
    } else {
      setError(""); // Clear error if password is valid
    }

    if (!minLength) {
      setError("Password must be at least 8 characters long");
      return;
    } else {
      setError(""); // Clear error if password is valid
    }
    // Step 2: Prepare user data for PHP backend

    const userData = { type: "REGISTER", name, username, email, password };

    try {
      console.log("Sending request to backend...", userData);

      // Step 3: Send data to PHP backend for RabbitMQ processing
      const response = await axios.post(getBackendURL(), userData, {
        withCredentials: true,
      });

      console.log("Response received:", response);
      const data = await response.data;

      console.log("Response received:", data);

      if (response.status !== 200) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      const result = response.data;

      // Step 4: Handle response
      if (result.success) {
        setSuccess(result.success);
        setError("");
        // Redirect to login page
        window.location.href = "/";
      } else {
        setError(result.error);
        setSuccess("");
      }
    } catch (err) {
      console.error("Fetch error:", err);
      setError("Failed to connect to the server.");
      setSuccess("");
    }
  }

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

        <h2 className="my-4 ">Please Register</h2>
      </div>

      <form onSubmit={handleSubmission}>
        <div>
          <input
            type="text"
            value={name}
            placeholder="Name"
            onChange={(e) => setName(e.target.value)}
            required
          />
        </div>
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
            type="email"
            value={email}
            placeholder="Email"
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </div>
        <div>
          <input
            id="password"
            value={password}
            type={visible ? "text" : "password"}
            placeholder="Password"
            onChange={(e) => setPassword(e.target.value)}
            required
          />
          <div
            className="password container"
            onClick={() => setVisible(!visible)}
            style={{ cursor: "pointer" }}
          >
            {visible ? "Hide" : "Show"} Confirm Password
          </div>
        </div>
        <div>
          <input
            id="password_verify"
            value={confirmPassword}
            type={visible ? "text" : "password"}
            placeholder="Confirm Password"
            onChange={(e) => setConfirmPassword(e.target.value)}
            required
          />
          <div
            className="password container"
            onClick={() => setVisible(!visible)}
            style={{ cursor: "pointer" }}
          >
            {visible ? "Hide" : "Show"} Confirm Password
          </div>
        </div>

        {/* Show Error & Success Messages */}
        {error && <p style={{ color: "red" }}>{error}</p>}
        {success && <p style={{ color: "green" }}>{success}</p>}

        <button type="submit" className="btn btn-success mt-2 px-5">
          REGISTER
        </button>
      </form>
    </div>
  );
}

export default RegisterPage;
