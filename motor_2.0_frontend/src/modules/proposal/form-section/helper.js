import Compressor from "compressorjs";
import { PDFDocument } from "pdf-lib";
import swal from "sweetalert";

export const handleCompress = async (imageFile, setPhoto) => {
  new Compressor(imageFile, {
    quality: 0.6,
    success(result) {
      setPhoto(result);
      document.getElementById("image-preview") &&
        document.getElementById("image-preview").click();
    },
    error(err) {
      swal("Error", err, "error");
    },
  });
};

export const compressPDF = async (file, setPhoto) => {
  try {
    const pdfBytes = await file.arrayBuffer();
    const pdfDoc = await PDFDocument.load(pdfBytes);

    const modifiedPdfBytes = await pdfDoc.save();

    const compressedBlob = new Blob([modifiedPdfBytes], {
      type: "application/pdf",
    });

    const compressedFile = new File([compressedBlob], file.name, {
      type: "application/pdf",
      lastModified: new Date().getTime(),
    });
    return compressedFile;
  } catch (error) {
    setPhoto();
    swal("Error", error, "error");
  }
};

export const calculateExpression = (expression) => {
  const operators = /[*/+-]/g;
  const parts = expression?.split(operators);
  const ops = expression?.match(operators);

  if (parts.length === 1) {
    return parseFloat(parts[0]);
  }

  let result = parseFloat(parts[0]);
  ops.forEach((operator, index) => {
    const nextNumber = parseFloat(parts[index + 1]);
    if (operator === "*") {
      result *= nextNumber;
    } else if (operator === "/") {
      result /= nextNumber;
    } else if (operator === "+") {
      result += nextNumber;
    } else if (operator === "-") {
      result -= nextNumber;
    }
  });

  return result;
};