import React, { useState } from "react";

function HomePage() {
    const [image, setImage] = useState("");
    const handleImageChange = (event) =>{
        const file = event.target.files[0];
        if (file){
            const reader = new FileReader();
            reader.onload = (e) => setImage(e.target.result);
            reader.readAsDataURL(file);
        }    
    }

    return (
        <div className="homepage container">
        <h1>
            Home Page
        </h1>
        <div class = "profile-pic">
            <label class="upload-label" for="file">
                <span class="glyphicon glyphicon-camera">=
                </span>
            </label>
            <input id="file" type="file" onChange={handleImageChange} style ={{display:"none"}}/>
            <img src = {image || "/home/hanna/Downloads/istockphoto-1300845620-612x612.jpg"} id="output" alt="Profile" width="200" />
           </div>
        </div>
    );
}

export default HomePage;