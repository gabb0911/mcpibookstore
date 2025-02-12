/**
 * Import function triggers from their respective submodules:
 *
 * const { onCall } = require("firebase-functions/v2/https");
 * const { onDocumentWritten } = require("firebase-functions/v2/firestore");
 *
 * See a full list of supported triggers at https://firebase.google.com/docs/functions
 */

const { getAuth } = require("firebase/auth");
const { getFirestore, doc, setDoc } = require("firebase/firestore");

const { onRequest } = require("firebase-functions/v2/https");
const admin = require("firebase-admin");
const functions = require("firebase-functions");

admin.initializeApp();

exports.setRoleInFirestore = functions.https.onRequest(async (request, response) => {
  // Security check: Only authenticated admins can change roles
  const authToken = request.headers.authorization?.split('Bearer ')[1];
  
  if (!authToken) {
    return response.status(403).send("Unauthorized request.");
  }

  try {
    const decodedToken = await admin.auth().verifyIdToken(authToken);
    if (decodedToken.role !== "admin") {
      throw new Error("Permission denied: Only admins can assign roles.");
    }
  } catch (error) {
    return response.status(403).send(error.message);
  }

  const { email, role } = request.body;

  if (!email || !role) {
    return response.status(400).send("Missing required parameters: email and newRole.");
  }

  try {
    const user = await admin.auth().getUserByEmail(email);
    const userUid = user.uid;

    // Update Firestore document for user role
    await admin.firestore().collection("users").doc(userUid).set(
      { role: newRole },
      { merge: true }
    );

    // Set custom claims for user authentication
    await admin.auth().setCustomUserClaims(userUid, { role: newRole });

    response.send({ message: `Role of ${email} updated to ${newRole}.` });
  } catch (error) {
    response.status(500).send({ error: error.message });
  }
});



// Example function template
// exports.helloWorld = onRequest((request, response) => {
//   logger.info("Hello logs!", { structuredData: true });
//   response.send("Hello from Firebase!");
// });
