import React, { useState } from "react";
import axios from "axios"

function RegisterPage() {
    const [name, setName] = useState("");
    const [username, setUsername] = useState("");
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [confirmPassword, setConfirmPassword] = useState("");
    const [error, setError] = useState("");
    const [success, setSuccess] = useState("");

    async function handleSubmission(event) {
        event.preventDefault(); // Prevent form submission
    
        // Step 1: Validate Passwords Match
        if (password !== confirmPassword) {
            setError("Passwords do not match. Please recheck.");
            return;
        } else {
            setError(""); // Clear error if passwords match
        }
    
        // Step 2: Prepare user data for PHP backend
        const userData = { name, username, email, password };

    
        try {
            console.log("Sending request to backend...");
    
            // Step 3: Send data to PHP backend for RabbitMQ processing
            const response = await axios.post("http://localhost/backend/register.php", userData);

    
            console.log("Response received:", response.data);
            console.log(response)
    
            if (response.status !== 200) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            console.log("eror noooo")
            const result = await response.data;
            console.log("Result:", result);
    
            // Step 4: Handle response
            if (result.success) {
                setSuccess(result.success);
                setError(""); 
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
            <h1>Welcome to InvestZero!</h1>
            <h2>Please Register</h2>

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
                        type="password"
                        value={password}
                        placeholder="Password"
                        onChange={(e) => setPassword(e.target.value)}
                        required
                    />
                </div>
                <div>
                    <input
                        type="password"
                        value={confirmPassword}
                        placeholder="Confirm Password"
                        onChange={(e) => setConfirmPassword(e.target.value)}
                        required
                    />
                </div>

                {/* Show Error & Success Messages */}
                {error && <p style={{ color: "red" }}>{error}</p>}
                {success && <p style={{ color: "green" }}>{success}</p>}

                <button type="submit">Register</button>
            </form>
        </div>
    );
}

export default RegisterPage;
