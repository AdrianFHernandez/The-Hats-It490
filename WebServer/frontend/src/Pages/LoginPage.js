import React, { useState } from "react";
import axios from "axios"; 

function LoginPage() {
    const [username, setUsername] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState("");

    const handleSubmission = async (event) => {
        event.preventDefault();
        
        // Make a POST request to the server 
        try {
            const response = await axios.post("http://www.sample.com/server.php", {
                type: "login", 
                username: username,
                password: password,
                sessionId: "1234"
            });
            
            window.alert(JSON.stringify(response.data));
            if (response.data.returnCode == "0"){
                // Login successful
                // Redirect to the dashboard page
                window.location.href = "https://www.google.com";
            }

            


        } catch (error) {
            console.error("Error during login:", error);
            setError("Error during login. Please try again.");
            setPassword(""); 
        }
    };

    return (
        <div>
            <h1>Welcome to InvestZero!</h1>
            <h2>Please Login</h2>
            {error && <div><h3>{error}</h3></div>}
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
                <button type="submit">Login</button>
            </form>
        </div>
    );
}

export default LoginPage;
