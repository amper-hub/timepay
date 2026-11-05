const fs = require("node:fs");
const path = require("node:path");

const modulePath = path.join(
  __dirname,
  "..",
  "node_modules",
  "@react-native-ml-kit",
  "face-detection",
  "android",
  "src",
  "main",
  "java",
  "com",
  "rnmlkit",
  "facedetection",
  "FaceDetectionModule.java"
);

if (!fs.existsSync(modulePath)) {
  console.log("ML Kit Android source is not installed; skipping detector lifecycle patch.");
  process.exit(0);
}

const source = fs.readFileSync(modulePath, "utf8");
if (source.includes("// TimePay: close the per-image detector after processing.")) {
  process.exit(0);
}

const success = `                                    WritableArray result = Arguments.createArray();
                                    for (Face face : faces) {
                                        result.pushMap(faceToMap(face, optionsMap));
                                    }
                                    promise.resolve(result);`;
const patchedSuccess = `                                    try {
                                        WritableArray result = Arguments.createArray();
                                        for (Face face : faces) {
                                            result.pushMap(faceToMap(face, optionsMap));
                                        }
                                        promise.resolve(result);
                                    } finally {
                                        // TimePay: close the per-image detector after processing.
                                        detector.close();
                                    }`;
const failure = `                                    e.printStackTrace();
                                    promise.reject("Face detection failed", e);`;
const patchedFailure = `                                    try {
                                        e.printStackTrace();
                                        promise.reject("Face detection failed", e);
                                    } finally {
                                        detector.close();
                                    }`;

if (!source.includes(success) || !source.includes(failure)) {
  throw new Error("Installed ML Kit source did not match the expected 2.0.1 detector lifecycle.");
}

fs.writeFileSync(modulePath, source.replace(success, patchedSuccess).replace(failure, patchedFailure));
