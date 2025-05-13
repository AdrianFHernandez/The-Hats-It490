import React, { useEffect, useState } from "react";
import {
  Container,
  Spinner,
  Alert,
  Table,
  Card,
  Button,
} from "react-bootstrap";
import axios from "axios";
import Navbar from "../Components/Navbar";
import getBackendURL from "../Utils/backendURL";

import {
  PDFViewer,
  PDFDownloadLink,
  Document,
  Page,
  Text,
  View,
  StyleSheet,
} from "@react-pdf/renderer";

// PDF Styles
const styles = StyleSheet.create({
  page: { padding: 30 },
  section: { marginBottom: 12 },
  title: { fontSize: 18, fontWeight: "bold", marginBottom: 10 },
  text: { fontSize: 12 },
  table: { marginTop: 10, borderWidth: 1, borderColor: "#000" },
  row: { flexDirection: "row" },
  cell: {
    flex: 1,
    padding: 4,
    borderRightWidth: 1,
    borderBottomWidth: 1,
    fontSize: 11,
  },
});

// PDF Component
const PDFContent = ({ userData }) => (
  <Document>
    <Page size="A4" style={styles.page}>
      <View style={styles.section}>
        <Text style={styles.title}>Form 1099-K - InvestZero</Text>
        <Text style={styles.text}>Tax Year: 2024</Text>
      </View>

      <View style={styles.section}>
        <Text style={styles.title}>Recipient Info</Text>
        <Text style={styles.text}>Name: {userData.name}</Text>
        <Text style={styles.text}>Email: {userData.email}</Text>
        <Text style={styles.text}>Phone: {userData.phone}</Text>
      </View>

      <View style={styles.section}>
        <Text style={styles.title}>Transactions</Text>
        <View style={styles.row}>
          <Text style={styles.cell}>Date</Text>
          <Text style={styles.cell}>Ticker</Text>
          <Text style={styles.cell}>Type</Text>
          <Text style={styles.cell}>Qty</Text>
          <Text style={styles.cell}>Price</Text>
          <Text style={styles.cell}>Proceeds</Text>
        </View>
        {userData.transactions.map((t, i) => (
          <View style={styles.row} key={i}>
            <Text style={styles.cell}>{t.date}</Text>
            <Text style={styles.cell}>{t.ticker}</Text>
            <Text style={styles.cell}>{t.transaction_type}</Text>
            <Text style={styles.cell}>{t.quantity}</Text>
            <Text style={styles.cell}>${t.price}</Text>
            <Text style={styles.cell}>${t.proceeds}</Text>
          </View>
        ))}
      </View>
    </Page>
  </Document>
);

export default function TaxDocument({ handleLogout }) {
  const [userData, setUserData] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const fetchTaxData = async () => {
    setLoading(true);
    setError("");

    try {
      const response = await axios.post(
        getBackendURL(),
        { type: "GET_TAX_1099K" },
        { withCredentials: true }
      );

      if (response.status === 200 && response.data.user && response.data.transactions) {
        const enrichedTx = response.data.transactions.map((t) => ({
          ...t,
          proceeds: (t.quantity * t.price).toFixed(2),
        }));

        setUserData({
          name: response.data.user.name,
          email: response.data.user.email,
          phone: response.data.user.phone,
          transactions: enrichedTx,
        });
      } else {
        setError("Failed to retrieve tax data.");
      }
    } catch (err) {
      setError("Error loading tax data.");
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTaxData();
  }, []);

  return (
    <div>
      <Navbar handleLogout={handleLogout} />

      <Container className="py-4 text-white">
        <h2 className="mb-4">📄 Your 1099-K Tax Statement</h2>

        {loading && (
          <div className="text-center my-4">
            <Spinner animation="border" role="status" />
            <p className="mt-2">Loading...</p>
          </div>
        )}

        {error && <Alert variant="danger">{error}</Alert>}

        {!loading && userData && (
          <>
            <Card className="mb-4">
              <Card.Body>
                <h5>User Info</h5>
                <p><strong>Name:</strong> {userData.name}</p>
                <p><strong>Email:</strong> {userData.email}</p>
                <p><strong>Phone:</strong> {userData.phone}</p>
              </Card.Body>
            </Card>

            

            <div className="my-4">
              <h5>🖨 PDF Preview</h5>
              <div style={{ height: "600px", border: "1px solid #ccc", marginBottom: "10px" }}>
                <PDFViewer width="100%" height="100%">
                  <PDFContent userData={userData} />
                </PDFViewer>
              </div>

              <PDFDownloadLink
                document={<PDFContent userData={userData} />}
                fileName="1099K_InvestZero.pdf"
              >
                {({ loading }) =>
                  loading ? (
                    "Preparing PDF..."
                  ) : (
                    <Button variant="primary">Download 1099-K PDF</Button>
                  )
                }
              </PDFDownloadLink>
            </div>
          </>
        )}
      </Container>
    </div>
  );
}