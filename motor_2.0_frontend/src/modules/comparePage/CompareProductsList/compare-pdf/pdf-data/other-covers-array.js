export const getOtherCoversArray = (otherCoverProps) => {
  const { temp_data, newGroupedQuotesCompare } = otherCoverProps;

  let otherCoversArray = [];
  if (temp_data?.ownerTypeId === 2) {
    otherCoversArray.push(["Legal Liability To Employee"]);
  }
  if (temp_data?.ownerTypeId === 2) {
    otherCoversArray.push([
      newGroupedQuotesCompare[0]?.otherCovers?.legalLiabilityToEmployee ===
      undefined
        ? "Not Available"
        : newGroupedQuotesCompare[0]?.otherCovers?.legalLiabilityToEmployee ===
          0
        ? "Included"
        : `₹ ${newGroupedQuotesCompare[0]?.otherCovers?.legalLiabilityToEmployee}`,
    ]);
    otherCoversArray.push([
      newGroupedQuotesCompare[1]?.otherCovers?.legalLiabilityToEmployee ===
      undefined
        ? "Not Available"
        : newGroupedQuotesCompare[1]?.otherCovers?.legalLiabilityToEmployee ===
          0
        ? "Included"
        : `₹ ${newGroupedQuotesCompare[1]?.otherCovers?.legalLiabilityToEmployee}`,
    ]);
  }
  if (Number(newGroupedQuotesCompare[2]?.idv) > 0) {
    otherCoversArray.push([
      newGroupedQuotesCompare[2]?.otherCovers?.legalLiabilityToEmployee ===
      undefined
        ? "Not Available"
        : newGroupedQuotesCompare[2]?.otherCovers?.legalLiabilityToEmployee ===
          0
        ? "Included"
        : `₹ ${newGroupedQuotesCompare[2]?.otherCovers?.legalLiabilityToEmployee}`,
    ]);
  }
  return otherCoversArray;
};
