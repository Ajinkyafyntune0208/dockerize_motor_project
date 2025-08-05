import * as math from "mathjs";

export function parseFormulas(formulas, data) {
  // Function to replace variables in the expression with actual values
  function replaceVariables(exp, data) {
    let expression = exp;
    function safeValue(value) {
      // Return a default value if the value is null or undefined
      return value !== null && value !== undefined ? value : 0;
    }
    // Handle rounding operations in a controlled order
    const multipleIterations = (noOfIterations) => {
      [...Array(noOfIterations)].forEach(() => {
        expression = expression
          .replace(/(\([^)]+\))~ceil/g, (match, group1) => {
            return `Math.ceil${group1}`;
          })
          .replace(
            /\(([^)]+)\)~ceil/g,
            (match, group1) => `Math.ceil(${group1}`
          )
          .replace(/(\([^~]*\))~ceil/g, (match, group1) => {
            return `Math.ceil${group1}`;
          })
          .replace(/(\([^)]+\))~floor/g, (match, group1) => {
            return `Math.floor${group1}`;
          })
          .replace(
            /\(([^)]+)\)~floor/g,
            (match, group1) => `Math.floor(${group1})`
          )
          .replace(/(\([^~]*\))~floor/g, (match, group1) => {
            return `Math.floor${group1}`;
          })
          .replace(/(\([^)]+\))~round/g, (match, group1) => {
            return `Math.round${group1}`;
          })
          .replace(/(\([^~]*\))~round/g, (match, group1) => {
            return `Math.round${group1}`;
          })
          .replace(
            /\(([^)]+)\)~round/g,
            (match, group1) => `Math.round(${group1})`
          );
      });
    };

    multipleIterations(2);
    expression = expression
      .replace(/Math.roundMath.round/g, "Math.round")
      .replace(/Math.ceilMath.ceil/g, "Math.ceil")
      .replace(/Math.floorMath.floor/g, "Math.floor");
    data?.companyAlias === "united_india" &&
      console.log("expression", expression);
    let discountBucketKeys = data?.buckets ? Object.keys(data?.buckets) : [];
    let discountPercentageKeys = discountBucketKeys.map(
      (key) => `${key}_discount_percent`
    );

    expression = expression.replace(
      /\b(?!\d+(\.\d+)?\b)(?!Math\b)(?!ceil\b)(?!floor\b)(?!round\b)(?!intersection\b)\w+\b/g,
      (match) => {
        const value = data[match];
        // Handle array variables
        if ([...discountBucketKeys, "UserSelectedAddons"].includes(match)) {
          if (Array.isArray(value)) {
            return JSON.stringify(value); // Convert array to string representation
          } else {
            return "[]"; // Default to empty array representation if not an array
          }
        }

        // Handle discount percentages
        if ([...discountPercentageKeys].includes(match)) {
          if (value !== undefined && value !== null) {
            return value; // Return the actual discount percentage
          } else {
            return "0"; // Return '0' if the value is not found
          }
        }

        // Handle other variables
        return safeValue(value);
      }
    );
    return expression;
  }

  // Function to evaluate the expression safely
  function evaluateExpression(expression, key) {
    // Replace variable names with their values
    let processedExpr = replaceVariables(expression, data);

    // Handle intersection
    if (processedExpr.includes("intersection")) {
      processedExpr = processedExpr.replace(
        /(\[.*?\]) intersection (\[.*?\])/g,
        (match, array1, array2) => {
          const arr1 = JSON.parse(array1);
          const arr2 = JSON.parse(array2);
          const intersection = arr1.filter((value) => arr2.includes(value));
          return JSON.stringify(intersection.length);
        }
      );
    }

    // Handle JavaScript operators
    const areJSOperatorsPresent =
      processedExpr.includes("&&") ||
      processedExpr.includes("||") ||
      processedExpr.includes("==") ||
      processedExpr.includes("<=") ||
      processedExpr.includes(">=") ||
      processedExpr.includes("Math") ||
      processedExpr.includes("===");

    if (areJSOperatorsPresent) {
      return new Function(`return ${processedExpr}`)();
    } else {
      return math.evaluate(processedExpr);
    }
  }

  // Result object to hold evaluated values
  const result = {};

  // Process each formula
  for (const [key, formula] of Object.entries(formulas)) {
    if (formula && key) {
      result[key] = evaluateExpression(formula, key);
    }
  }

  return result;
}
