import React, { useEffect, useState } from "react";


function HomePage() {
    const [userData, setUserData] = useState("");

    useEffect(() => {
        setUserData(props.user)
      }, []);
      
    

    return (
        <div className="homepage container">
        <h1>
            Home Page
        </h1>
            {JSON.stringify(userData)}
        </div>
    );
}

export default HomePage;