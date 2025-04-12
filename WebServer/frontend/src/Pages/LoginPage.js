import React, { useState } from "react";
import axios from "axios";
import getBackendURL from "../Utils/backendURL";

function LoginPage() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [otp, setOtp] = useState("");
  const [isOtpModalVisible, setIsOtpModalVisible] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  // Handle OTP verification
  const handleOtpSubmission = async (event) => {
    event.preventDefault();
    setIsLoading(true);
    setError("");

    try {
      const response = await axios.post(
        getBackendURL(),
        { type: "VERIFY_OTP", OTP_code: otp },
        { withCredentials: true }
      );

      console.log("OTP VERIFIED:", response.data);

      if (response.data.success) {
        window.location.href = "/home";
      } else {
        setError("Invalid OTP. Please try again.");
      }
    } catch (error) {
      console.error("Error verifying OTP:", error);
      setError("Error verifying OTP. Please try again.");
    } finally {
      setIsLoading(false);
    }
  };

  // Handle login
  const handleSubmission = async (event) => {
    event.preventDefault();
    setIsLoading(true);
    setError("");

    try {
      const response = await axios.post(
        getBackendURL(),
        { type: "LOGIN", username, password },
        { withCredentials: true }
      );

      console.log("LOGIN RESPONSE:", response.data);

      if (response.data.success) {
        setIsOtpModalVisible(true); // Show OTP input
      } else {
        setError("Login failed. Please try again.");
      }
    } catch (error) {
      console.error("Error during login:", error);
      setPassword("");
      setError("Error during login. Please try again.");
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="login-container">
      {/* Intro + Login */}
      {!isOtpModalVisible && (
        <>
          <div className="mt-5">
            <h1 className="mb-3 fw-bold text-center text-truncate">
              Welcome to InvestZero!
            </h1>
            <h4 className="mb-4 w-100 text-center fs-6 text-light">
              Practice investing for free and learn to grow your wealth!
            </h4>
            <h2 className="my-4">Please LOGIN</h2>
          </div>

          {error && (
            <div>
              <h3 className="text-danger">{error}</h3>
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
            <button
              type="submit"
              className="btn btn-success mt-2 px-5"
              disabled={isLoading}
            >
              {isLoading ? "Logging in..." : "LOGIN"}
            </button>
          </form>
        </>
      )}

{isOtpModalVisible && (
  <div
    className="modal fade show"
    tabIndex="-1"
    style={{
      display: "block",
      backgroundColor: "rgba(0,0,0,0.5)",
    }}
  >
    <div className="modal-dialog modal-dialog-centered">
      <div className="modal-content p-4">
        <h2 className="text-center mb-3">Enter code sent to your phone</h2>
        <form onSubmit={handleOtpSubmission}>
          <div className="mb-3">
            <input
              type="text"
              className="form-control text-center fs-4"
              value={otp}
              onChange={(e) => setOtp(e.target.value)}
              placeholder="6-digit OTP"
              maxLength="6"
              required
            />
          </div>
          <button
            type="submit"
            className="btn btn-primary w-100"
            disabled={isLoading}
          >
            {isLoading ? "Verifying..." : "Verify OTP"}
          </button>
        </form>
        {error && (
          <div className="text-danger text-center mt-3">{error}</div>
        )}
      </div>
    </div>
  </div>
)}
    </div>
  );
}

export default LoginPage;
