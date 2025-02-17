import React, { useState } from "react";
import hash from "../Functions/hash";
import salt from "../Functions/salt";

function RegisterPage() {
    const [name, setName] = useState("");
    const [username, setUsername] = useState("");
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [confirmPassword, setConfirmPassword] = useState("");
    const [error, setError] = useState(""); 

    function handleSubmission(event) {
        event.preventDefault(); // Prevent form submission

        if (password !== confirmPassword) {
            setError("Passwords do not match. Please recheck."); 
            return;
        } else {
            setError(""); // Clear error if passwords match
        }

        // Second Step is to check if username and email in the db:
        // if they are, alert user and tell them to use different username and/or password this makes sure uniquness

        // Third step is to hash password and generate salt
        let extra = salt()
        let hashedPassword = hash(password)

        //Send these through rabbitmq to the db to store this
        

        //Generate Session

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
                        placeholder="Confirm your Password"
                        onChange={(e) => setConfirmPassword(e.target.value)}
                        required
                    />
                </div>

                {error && <p style={{ color: "red" }}>{error}</p>}

                <button type="submit">Register</button>
            </form>
        </div>
    );
}

export default RegisterPage;
