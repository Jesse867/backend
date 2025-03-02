export default async function handler(req, res) {
    if (req.method !== "POST") {
        return res.status(405).json({ message: "Method Not Allowed" });
    }

    try {
        const response = await fetch("http://localhost/hospital_api/staff_login.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(req.body),
        });

        const data = await response.json();
        
        if (response.ok) {
            // Check if the user has a valid staff role
            if (data.role && ['doctor', 'pharmacist', 'billing_officer', 'receptionist', 'admin'].includes(data.role)) {
                res.status(200).json({
                    message: "Login successful",
                    role: data.role,
                    user_id: data.user_id
                });
            } else {
                res.status(403).json({ message: "Unauthorized access" });
            }
        } else {
            res.status(response.status).json(data);
        }
    } catch (error) {
        console.error("Error in staff login API route:", error);
        res.status(500).json({ message: "Server Error", error: error.message });
    }
}