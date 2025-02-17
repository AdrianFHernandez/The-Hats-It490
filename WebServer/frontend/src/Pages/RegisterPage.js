import React from "react"
import { useState } from "react"

function RegisterPage (){

    const [name, setName] = useState("")
    const [username, setUsername] = useState("")
    const [email, setEmail] = useState("")
    const [password, setPassword] = useState("")
    const [confirmPassword, setConfirmPassword] = useState("")

    function handleSubmisson(event){

        // Still need to add a few things to registration:
        // • Password checking with confirmPassword
        // • Algo for the hashing of passwords
        // • Storing unique salt in DB
        // • Storing hashed Password value in DB

        event.preventDefault(); 

        console.log(name)
        console.log(username)
        console.log(email)
        console.log(password)
        console.log(confirmPassword)
    }


    return (
        <div>
            <h1>Welcome to InvestZero!</h1>
            <h2>Please Register</h2>
            
            <form onSubmit={handleSubmisson}>
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
                <button type="submit" >Register </button>

                </form>

        </div>

    )

}


export default RegisterPage