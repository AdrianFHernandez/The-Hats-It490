import React, { useEffect, useState } from "react";


function HomePage(props) {
    const [userData, setUserData] = useState(null);

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